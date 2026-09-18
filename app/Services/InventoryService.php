<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Barang;
use App\Models\DetailTransaksi;
use App\Models\StockBatch;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pusat logika inventaris: penerimaan barang (pembentukan batch),
 * konsumsi stok bermetode FIFO (First In First Out) berbasis tanggal
 * penerimaan batch, pemulihan stok saat transaksi dibatalkan, dan
 * pencatatan penyusutan (write-off) untuk batch yang kedaluwarsa/rusak.
 *
 * Kolom `barangs.stok` tetap dipertahankan sebagai cache agregat agar
 * query daftar/dashboard tetap cepat, tetapi sumber kebenaran jumlah
 * stok sesungguhnya adalah SUM(stock_batches.qty_tersisa).
 */
class InventoryService
{
    /**
     * Total stok yang benar-benar tersedia untuk sebuah barang,
     * dihitung langsung dari sisa batch (bukan dari kolom cache).
     */
    public function stokTersedia(Barang $barang): int
    {
        return (int) $barang->stockBatches()->sum('qty_tersisa');
    }

    /**
     * Terima barang baru (stok masuk) dan bentuk satu batch baru.
     *
     * @param  array{qty:int, harga_beli_satuan?:int, tanggal_terima?:string, tanggal_kedaluwarsa?:string|null, supplier?:string|null, catatan?:string|null, reason?:string, reference?:EloquentModel|null}  $data
     */
    public function terimaBarang(Barang $barang, array $data): StockBatch
    {
        return DB::transaction(function () use ($barang, $data) {
            $qty = (int) $data['qty'];

            $batch = StockBatch::create([
                'barang_id' => $barang->id,
                'tanggal_terima' => $data['tanggal_terima'] ?? now()->toDateString(),
                'tanggal_kedaluwarsa' => $data['tanggal_kedaluwarsa'] ?? null,
                'qty_masuk' => $qty,
                'qty_tersisa' => $qty,
                'harga_beli_satuan' => $data['harga_beli_satuan'] ?? $barang->harga_beli,
                'supplier' => $data['supplier'] ?? null,
                'catatan' => $data['catatan'] ?? null,
            ]);

            $barang->increment('stok', $qty);

            $this->catatPergerakan($barang, $batch, 'in', $data['reason'] ?? 'pembelian', $qty, $batch->harga_beli_satuan, $data['reference'] ?? null, $data['catatan'] ?? null);

            return $batch;
        });
    }

    /**
     * Konsumsi stok secara FIFO: ambil dari batch dengan tanggal_terima
     * paling lama lebih dulu, lalu lanjut ke batch berikutnya jika batch
     * pertama belum cukup. Mengembalikan rincian batch mana saja beserta
     * qty & harga pokok yang dipakai (dipakai untuk HPP akurat).
     *
     * @return Collection<int, array{batch: StockBatch, qty:int, harga_beli_satuan:int}>
     *
     * @throws InsufficientStockException
     */
    public function konsumsiStok(Barang $barang, int $qty, string $reason = 'penjualan', ?EloquentModel $reference = null, ?string $notes = null): Collection
    {
        return DB::transaction(function () use ($barang, $qty, $reason, $reference, $notes) {
            $barang->refresh();

            $batches = $barang->stockBatches()
                ->aktif()
                ->urutanFifo()
                ->lockForUpdate()
                ->get();

            $tersedia = (int) $batches->sum('qty_tersisa');

            if ($tersedia < $qty) {
                throw InsufficientStockException::forBarang($barang->nama_barang, $qty, $tersedia);
            }

            $sisaDibutuhkan = $qty;
            $rincian = collect();

            foreach ($batches as $batch) {
                if ($sisaDibutuhkan <= 0) {
                    break;
                }

                $ambil = min($batch->qty_tersisa, $sisaDibutuhkan);
                $batch->decrement('qty_tersisa', $ambil);
                $sisaDibutuhkan -= $ambil;

                $rincian->push([
                    'batch' => $batch,
                    'qty' => $ambil,
                    'harga_beli_satuan' => $batch->harga_beli_satuan,
                ]);

                $this->catatPergerakan($barang, $batch, 'out', $reason, $ambil, $batch->harga_beli_satuan, $reference, $notes);
            }

            $barang->decrement('stok', $qty);

            return $rincian;
        });
    }

    /**
     * Kembalikan stok yang sebelumnya dikonsumsi oleh satu baris detail
     * transaksi (dipakai saat transaksi dibatalkan). Qty dikembalikan
     * persis ke batch asalnya agar riwayat FIFO tetap konsisten.
     */
    public function pulihkanStokDetailTransaksi(DetailTransaksi $detail, string $reason = 'pembatalan'): void
    {
        DB::transaction(function () use ($detail, $reason) {
            $detail->loadMissing(['batches.stockBatch', 'barang']);
            $barang = $detail->barang;

            foreach ($detail->batches as $penggunaan) {
                $batch = $penggunaan->stockBatch;
                $batch->increment('qty_tersisa', $penggunaan->qty);
                $barang->increment('stok', $penggunaan->qty);

                $this->catatPergerakan($barang, $batch, 'in', $reason, $penggunaan->qty, $penggunaan->harga_beli_satuan, $detail->transaksi, 'Pembatalan transaksi');
            }
        });
    }

    /**
     * Catat penyusutan (write-off) untuk sebagian atau seluruh sisa
     * sebuah batch — misalnya karena kedaluwarsa, rusak, atau hilang.
     */
    public function catatPenyusutan(StockBatch $batch, int $qty, string $alasan, ?string $catatan = null): StockMovement
    {
        return DB::transaction(function () use ($batch, $qty, $alasan, $catatan) {
            $batch->refresh();

            if ($qty <= 0 || $qty > $batch->qty_tersisa) {
                throw InsufficientStockException::forBarang($batch->barang->nama_barang, $qty, $batch->qty_tersisa);
            }

            $batch->decrement('qty_tersisa', $qty);
            $batch->barang->decrement('stok', $qty);

            return $this->catatPergerakan(
                $batch->barang,
                $batch,
                'out',
                'penyusutan',
                $qty,
                $batch->harga_beli_satuan,
                null,
                trim($alasan.($catatan ? " — {$catatan}" : ''))
            );
        });
    }

    protected function catatPergerakan(Barang $barang, ?StockBatch $batch, string $direction, string $reason, int $qty, ?int $hargaSatuan, ?EloquentModel $reference = null, ?string $notes = null): StockMovement
    {
        return StockMovement::create([
            'barang_id' => $barang->id,
            'stock_batch_id' => $batch?->id,
            'direction' => $direction,
            'reason' => $reason,
            'qty' => $qty,
            'harga_satuan' => $hargaSatuan,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'notes' => $notes,
        ]);
    }
}
