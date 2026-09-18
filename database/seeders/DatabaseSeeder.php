<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'ratih@toko.com'],
            ['name' => 'Ratih', 'password' => Hash::make('password')]
        );

        // 'exp' = selisih hari dari hari ini untuk tanggal_kedaluwarsa batch
        // awal (negatif = sudah lewat, null = tidak diisi/tidak relevan).
        // Sengaja dicampur supaya widget "Barang Kedaluwarsa" di dashboard
        // langsung kelihatan terisi begitu data demo ini di-seed.
        $dataBarang = [
            ['kode_barang' => 'SBK-001', 'nama_barang' => 'Minyak Goreng Bimoli 2L',    'kategori' => 'Sembako',     'satuan' => 'ktn',  'harga_beli' => 26000,  'harga_jual' => 28500,  'stok' => 4,  'stok_minimum' => 10, 'exp' => 3],
            ['kode_barang' => 'SBK-002', 'nama_barang' => 'Gula Pasir 1kg',             'kategori' => 'Sembako',     'satuan' => 'ktn',  'harga_beli' => 13500,  'harga_jual' => 14500,  'stok' => 8,  'stok_minimum' => 12, 'exp' => 180],
            ['kode_barang' => 'SBK-003', 'nama_barang' => 'Beras Pandan Wangi 5kg',     'kategori' => 'Sembako',     'satuan' => 'ktn',  'harga_beli' => 68000,  'harga_jual' => 72000,  'stok' => 45, 'stok_minimum' => 10, 'exp' => 150],
            ['kode_barang' => 'SBK-004', 'nama_barang' => 'Tepung Terigu Segitiga 1kg', 'kategori' => 'Sembako',     'satuan' => 'ktn',  'harga_beli' => 10500,  'harga_jual' => 11500,  'stok' => 30, 'stok_minimum' => 8, 'exp' => 120],
            ['kode_barang' => 'MIE-001', 'nama_barang' => 'Indomie Goreng',             'kategori' => 'Mie Instan',  'satuan' => 'dos',  'harga_beli' => 95000,  'harga_jual' => 102000, 'stok' => 6,  'stok_minimum' => 10, 'exp' => -2],
            ['kode_barang' => 'MIE-002', 'nama_barang' => 'Mie Sedaap Kuah',            'kategori' => 'Mie Instan',  'satuan' => 'dos',  'harga_beli' => 93000,  'harga_jual' => 100000, 'stok' => 22, 'stok_minimum' => 8, 'exp' => 90],
            ['kode_barang' => 'MNM-001', 'nama_barang' => 'Aqua Galon 19L',             'kategori' => 'Minuman',     'satuan' => 'pcs',  'harga_beli' => 20000,  'harga_jual' => 22000,  'stok' => 35, 'stok_minimum' => 5, 'exp' => 60],
            ['kode_barang' => 'MNM-002', 'nama_barang' => 'Teh Botol Sosro 330ml',      'kategori' => 'Minuman',     'satuan' => 'ktn',  'harga_beli' => 48000,  'harga_jual' => 54000,  'stok' => 28, 'stok_minimum' => 6, 'exp' => 5],
            ['kode_barang' => 'MNM-003', 'nama_barang' => 'Kopi Kapal Api Sachet',      'kategori' => 'Minuman',     'satuan' => 'bks',  'harga_beli' => 24000,  'harga_jual' => 27000,  'stok' => 10, 'stok_minimum' => 12, 'exp' => 6],
            ['kode_barang' => 'SNK-001', 'nama_barang' => 'Chitato Sapi Panggang 68g',  'kategori' => 'Snack',       'satuan' => 'dos',  'harga_beli' => 58000,  'harga_jual' => 65000,  'stok' => 24, 'stok_minimum' => 6, 'exp' => -1],
            ['kode_barang' => 'SNK-002', 'nama_barang' => 'Oreo Biskuit 137g',          'kategori' => 'Snack',       'satuan' => 'dos',  'harga_beli' => 52000,  'harga_jual' => 58000,  'stok' => 30, 'stok_minimum' => 6, 'exp' => 100],
            ['kode_barang' => 'KBR-001', 'nama_barang' => 'Sabun Cuci Piring Mama',     'kategori' => 'Produk Rumah', 'satuan' => 'ktn',  'harga_beli' => 22000,  'harga_jual' => 25000,  'stok' => 3,  'stok_minimum' => 8, 'exp' => 365],
            ['kode_barang' => 'KBR-002', 'nama_barang' => 'Rinso Cair 1.8L',            'kategori' => 'Produk Rumah', 'satuan' => 'ktn',  'harga_beli' => 32000,  'harga_jual' => 36000,  'stok' => 20, 'stok_minimum' => 6, 'exp' => 365],
            ['kode_barang' => 'KBR-003', 'nama_barang' => 'Sabun Mandi Lifebuoy 90g',   'kategori' => 'Produk Rumah', 'satuan' => 'ktn',  'harga_beli' => 6500,   'harga_jual' => 7500,   'stok' => 55, 'stok_minimum' => 10, 'exp' => 365],
            ['kode_barang' => 'RKK-001', 'nama_barang' => 'Gudang Garam Surya 16',      'kategori' => 'Rokok',       'satuan' => 'slop', 'harga_beli' => 220000, 'harga_jual' => 235000, 'stok' => 12, 'stok_minimum' => 3, 'exp' => null],
            ['kode_barang' => 'RKK-002', 'nama_barang' => 'Sampoerna Mild 16',          'kategori' => 'Rokok',       'satuan' => 'slop', 'harga_beli' => 235000, 'harga_jual' => 250000, 'stok' => 8,  'stok_minimum' => 3, 'exp' => null],

            // Tambahan snack & minuman populer di warung Indonesia, buat
            // memperkaya variasi data demo/seeder (bukan fitur baru).
            ['kode_barang' => 'SNK-003', 'nama_barang' => 'Taro Net Sapi Panggang 30g', 'kategori' => 'Snack',   'satuan' => 'dos', 'harga_beli' => 45000, 'harga_jual' => 50000, 'stok' => 18, 'stok_minimum' => 6, 'exp' => 45],
            ['kode_barang' => 'SNK-004', 'nama_barang' => 'Better Snack Jagung Bakar',  'kategori' => 'Snack',   'satuan' => 'dos', 'harga_beli' => 40000, 'harga_jual' => 45000, 'stok' => 5,  'stok_minimum' => 8, 'exp' => 200],
            ['kode_barang' => 'SNK-005', 'nama_barang' => 'Qtela Keripik Singkong Balado', 'kategori' => 'Snack', 'satuan' => 'dos', 'harga_beli' => 62000, 'harga_jual' => 69000, 'stok' => 15, 'stok_minimum' => 5, 'exp' => 4],
            ['kode_barang' => 'SNK-006', 'nama_barang' => 'Lays Kentang Original',      'kategori' => 'Snack',   'satuan' => 'dos', 'harga_beli' => 75000, 'harga_jual' => 84000, 'stok' => 10, 'stok_minimum' => 5, 'exp' => 90],
            ['kode_barang' => 'SNK-007', 'nama_barang' => 'Piattos Sapi Panggang',      'kategori' => 'Snack',   'satuan' => 'dos', 'harga_beli' => 60000, 'harga_jual' => 67000, 'stok' => 20, 'stok_minimum' => 6, 'exp' => 150],
            ['kode_barang' => 'SNK-008', 'nama_barang' => 'Beng-Beng',                  'kategori' => 'Snack',   'satuan' => 'dos', 'harga_beli' => 48000, 'harga_jual' => 54000, 'stok' => 25, 'stok_minimum' => 8, 'exp' => 120],
            ['kode_barang' => 'SNK-009', 'nama_barang' => 'Good Time Cookies Chocochips', 'kategori' => 'Snack', 'satuan' => 'dos', 'harga_beli' => 55000, 'harga_jual' => 62000, 'stok' => 12, 'stok_minimum' => 6, 'exp' => 200],
            ['kode_barang' => 'SNK-010', 'nama_barang' => 'Richeese Nabati Wafer',      'kategori' => 'Snack',   'satuan' => 'dos', 'harga_beli' => 42000, 'harga_jual' => 47000, 'stok' => 16, 'stok_minimum' => 6, 'exp' => 180],
            ['kode_barang' => 'MNM-004', 'nama_barang' => 'Teh Pucuk Harum 350ml',      'kategori' => 'Minuman', 'satuan' => 'ktn', 'harga_beli' => 42000, 'harga_jual' => 48000, 'stok' => 22, 'stok_minimum' => 6, 'exp' => 200],
            ['kode_barang' => 'MNM-005', 'nama_barang' => 'Pocari Sweat 350ml',         'kategori' => 'Minuman', 'satuan' => 'ktn', 'harga_beli' => 85000, 'harga_jual' => 95000, 'stok' => 14, 'stok_minimum' => 5, 'exp' => 250],
            ['kode_barang' => 'MNM-006', 'nama_barang' => 'Coca-Cola 390ml',            'kategori' => 'Minuman', 'satuan' => 'ktn', 'harga_beli' => 58000, 'harga_jual' => 65000, 'stok' => 18, 'stok_minimum' => 6, 'exp' => 270],
            ['kode_barang' => 'MNM-007', 'nama_barang' => 'Ultra Milk Coklat 250ml',    'kategori' => 'Minuman', 'satuan' => 'ktn', 'harga_beli' => 66000, 'harga_jual' => 75000, 'stok' => 9,  'stok_minimum' => 10, 'exp' => -1],
            ['kode_barang' => 'MNM-008', 'nama_barang' => 'Yakult',                     'kategori' => 'Minuman', 'satuan' => 'dos', 'harga_beli' => 45000, 'harga_jual' => 52000, 'stok' => 20, 'stok_minimum' => 8, 'exp' => 25],
            ['kode_barang' => 'MNM-009', 'nama_barang' => 'Nutrisari Jeruk Sachet',     'kategori' => 'Minuman', 'satuan' => 'bks', 'harga_beli' => 18000, 'harga_jual' => 21000, 'stok' => 30, 'stok_minimum' => 10, 'exp' => 300],
        ];

        $inventory = app(InventoryService::class);

        foreach ($dataBarang as $b) {
            $stokAwal = $b['stok'];
            $expHari = $b['exp'];
            unset($b['stok'], $b['exp']);

            $barang = Barang::firstOrCreate(
                ['kode_barang' => $b['kode_barang']],
                array_merge($b, ['stok' => 0])
            );

            // Barang baru (belum pernah di-seed sebelumnya) diberi stok awal
            // lewat batch FIFO yang sebenarnya, bukan sekadar mengisi kolom
            // stok, supaya data demo bisa langsung dijual lewat alur
            // transaksi yang sekarang berbasis batch.
            if ($barang->wasRecentlyCreated && $stokAwal > 0) {
                $inventory->terimaBarang($barang, [
                    'qty' => $stokAwal,
                    'harga_beli_satuan' => $b['harga_beli'],
                    'tanggal_kedaluwarsa' => $expHari !== null ? now()->addDays($expHari)->toDateString() : null,
                    'catatan' => 'Stok awal (seeder)',
                ]);
            }
        }
    }
}
