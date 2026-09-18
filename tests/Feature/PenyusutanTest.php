<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Barang;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;

test('writing off a batch reduces its remaining qty and the barang stock cache', function () {
    $barang = Barang::factory()->create(['stok' => 10]);
    $batch = StockBatch::factory()->for($barang)->create([
        'qty_masuk' => 10,
        'qty_tersisa' => 10,
        'harga_beli_satuan' => 5000,
    ]);

    $movement = app(InventoryService::class)->catatPenyusutan($batch, 4, 'Kedaluwarsa', 'Ditemukan saat stok opname');

    expect($batch->fresh()->qty_tersisa)->toBe(6);
    expect($barang->fresh()->stok)->toBe(6);
    expect($movement->reason)->toBe('penyusutan');
    expect($movement->direction)->toBe('out');
    expect($movement->qty)->toBe(4);
    expect($movement->harga_satuan)->toBe(5000);
});

test('writing off more than the batch has left is rejected', function () {
    $barang = Barang::factory()->create(['stok' => 3]);
    $batch = StockBatch::factory()->for($barang)->create(['qty_masuk' => 3, 'qty_tersisa' => 3]);

    expect(fn () => app(InventoryService::class)->catatPenyusutan($batch, 5, 'Rusak'))
        ->toThrow(InsufficientStockException::class);

    expect($batch->fresh()->qty_tersisa)->toBe(3);
});

test('the write-off form records shrinkage through the HTTP endpoint', function () {
    $user = User::factory()->create();
    $barang = Barang::factory()->create(['stok' => 8]);
    $batch = StockBatch::factory()->for($barang)->create(['qty_masuk' => 8, 'qty_tersisa' => 8]);

    $response = $this->actingAs($user)->post(route('barang.batch.penyusutan', [$barang, $batch]), [
        'qty' => 3,
        'alasan' => 'kedaluwarsa',
        'catatan' => 'Ditemukan basi',
    ]);

    $response->assertRedirect(route('barang.show', $barang));
    expect($batch->fresh()->qty_tersisa)->toBe(5);
    expect($barang->fresh()->stok)->toBe(5);
    expect(StockMovement::where('reason', 'penyusutan')->where('barang_id', $barang->id)->exists())->toBeTrue();
});

test('shrinkage appears in the penyusutan report with its rupiah value', function () {
    $user = User::factory()->create();
    $barang = Barang::factory()->create(['stok' => 10, 'nama_barang' => 'Yogurt Cup']);
    $batch = StockBatch::factory()->for($barang)->create(['qty_masuk' => 10, 'qty_tersisa' => 10, 'harga_beli_satuan' => 3000]);

    app(InventoryService::class)->catatPenyusutan($batch, 6, 'Kedaluwarsa');

    $response = $this->actingAs($user)->get(route('laporan.penyusutan'));

    $response->assertOk();
    $response->assertSee('Yogurt Cup');
    // 6 unit x Rp 3.000 = Rp 18.000
    $response->assertSee('18.000', escape: false);
});
