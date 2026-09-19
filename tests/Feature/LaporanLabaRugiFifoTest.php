<?php

use App\Models\Barang;
use App\Models\DetailTransaksi;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\InventoryService;

test('laba rugi HPP is calculated from the actual FIFO batch cost, not the current barang price', function () {
    $user = User::factory()->create();

    $barang = Barang::factory()->create(['stok' => 0, 'harga_beli' => 99000, 'harga_jual' => 15000]);
    $inventory = app(InventoryService::class);
    $inventory->terimaBarang($barang, ['qty' => 5, 'harga_beli_satuan' => 6000, 'tanggal_terima' => now()->subDays(2)->toDateString()]);
    $inventory->terimaBarang($barang, ['qty' => 5, 'harga_beli_satuan' => 7000, 'tanggal_terima' => now()->subDay()->toDateString()]);

    $this->actingAs($user)->post(route('transaksi.store'), [
        'status' => 'lunas',
        'total_bayar' => 120000,
        'items' => [
            ['barang_id' => $barang->id, 'qty' => 8, 'harga_satuan' => 15000, 'diskon' => 0],
        ],
    ]);

    $response = $this->actingAs($user)->get(route('laporan.laba-rugi', ['bulan' => now()->format('Y-m')]));

    $response->assertOk();

    $expectedHpp = 5 * 6000 + 3 * 7000;
    $response->assertViewHas('hpp', $expectedHpp);
});

test('laba rugi falls back to the barang price for legacy sales with no batch records', function () {
    $user = User::factory()->create();
    $barang = Barang::factory()->create(['stok' => 0, 'harga_beli' => 4000]);
    $transaksi = Transaksi::factory()->create([
        'status' => 'lunas',
        'user_id' => $user->id,
        'total_harga' => 50000,
    ]);
    DetailTransaksi::create([
        'transaksi_id' => $transaksi->id,
        'barang_id' => $barang->id,
        'qty' => 5,
        'harga_satuan' => 10000,
        'diskon' => 0,
        'subtotal' => 50000,
    ]);

    $response = $this->actingAs($user)->get(route('laporan.laba-rugi', ['bulan' => $transaksi->created_at->format('Y-m')]));

    $response->assertOk();

    $response->assertViewHas('hpp', 5 * 4000);
});
