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

class InventoryService
{
    public function stokTersedia(Barang $barang): int
    {
        return (int) $barang->stockBatches()->sum('qty_tersisa');
    }

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
