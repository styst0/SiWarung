<?php

namespace App\Console\Commands;

use App\Models\StockBatch;
use Illuminate\Console\Command;

/**
 * Peringatan otomatis: mendaftar batch barang yang sudah kedaluwarsa
 * atau akan kedaluwarsa dalam N hari ke depan. Dijalankan berkala lewat
 * scheduler (lihat routes/console.php) dan bisa juga dipanggil manual
 * oleh pemilik warung, misalnya sebelum belanja stok baru.
 */
class CekBarangKedaluwarsa extends Command
{
    protected $signature = 'barang:cek-kedaluwarsa {--hari=7 : Jumlah hari ke depan yang dianggap "akan kedaluwarsa"}';

    protected $description = 'Tampilkan daftar batch barang yang sudah/akan kedaluwarsa';

    public function handle(): int
    {
        $hari = (int) $this->option('hari');

        $sudahKedaluwarsa = StockBatch::with('barang')
            ->aktif()->sudahKedaluwarsa()
            ->orderBy('tanggal_kedaluwarsa')
            ->get();

        $akanKedaluwarsa = StockBatch::with('barang')
            ->aktif()->akanKedaluwarsa($hari)
            ->orderBy('tanggal_kedaluwarsa')
            ->get();

        if ($sudahKedaluwarsa->isEmpty() && $akanKedaluwarsa->isEmpty()) {
            $this->info('Tidak ada barang yang kedaluwarsa atau akan kedaluwarsa.');

            return self::SUCCESS;
        }

        if ($sudahKedaluwarsa->isNotEmpty()) {
            $this->error("Sudah kedaluwarsa ({$sudahKedaluwarsa->count()} batch):");
            $this->table(
                ['Barang', 'Batch', 'Kedaluwarsa', 'Sisa Stok'],
                $sudahKedaluwarsa->map(fn (StockBatch $b) => [
                    $b->barang->nama_barang,
                    $b->kode_batch,
                    $b->tanggal_kedaluwarsa->format('d M Y'),
                    "{$b->qty_tersisa} {$b->barang->satuan}",
                ])
            );
        }

        if ($akanKedaluwarsa->isNotEmpty()) {
            $this->warn("Akan kedaluwarsa dalam {$hari} hari ({$akanKedaluwarsa->count()} batch):");
            $this->table(
                ['Barang', 'Batch', 'Kedaluwarsa', 'Sisa Stok'],
                $akanKedaluwarsa->map(fn (StockBatch $b) => [
                    $b->barang->nama_barang,
                    $b->kode_batch,
                    $b->tanggal_kedaluwarsa->format('d M Y'),
                    "{$b->qty_tersisa} {$b->barang->satuan}",
                ])
            );
        }

        return self::SUCCESS;
    }
}
