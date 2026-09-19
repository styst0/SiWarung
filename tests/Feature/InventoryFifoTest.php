<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Barang;
use App\Models\DetailTransaksiBatch;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\InventoryService;

test('stock is consumed from the oldest batch first (FIFO)', function () {
    $barang = Barang::factory()->create(['stok' => 0]);

    $batchLama = StockBatch::factory()->for($barang)->create([
        'tanggal_terima' => now()->subDays(5)->toDateString(),
        'qty_masuk' => 10,
        'qty_tersisa' => 10,
        'harga_beli_satuan' => 1000,
    ]);
    $batchBaru = StockBatch::factory()->for($barang)->create([
        'tanggal_terima' => now()->subDays(2)->toDateString(),
        'qty_masuk' => 10,
        'qty_tersisa' => 10,
        'harga_beli_satuan' => 1200,
    ]);
    $barang->update(['stok' => 20]);

    $rincian = app(InventoryService::class)->konsumsiStok($barang, 15, 'penjualan');

    expect($rincian)->toHaveCount(2);
    expect($rincian[0]['batch']->id)->toBe($batchLama->id);
    expect($rincian[0]['qty'])->toBe(10);
    expect($rincian[0]['harga_beli_satuan'])->toBe(1000);
    expect($rincian[1]['batch']->id)->toBe($batchBaru->id);
    expect($rincian[1]['qty'])->toBe(5);

    expect($batchLama->fresh()->qty_tersisa)->toBe(0);
    expect($batchBaru->fresh()->qty_tersisa)->toBe(5);
    expect($barang->fresh()->stok)->toBe(5);

    expect(StockMovement::where('barang_id', $barang->id)->where('direction', 'out')->count())->toBe(2);
});

test('consuming stock throws when total available is insufficient and nothing changes', function () {
    $barang = Barang::factory()->create(['stok' => 5]);
    $batch = StockBatch::factory()->for($barang)->create(['qty_masuk' => 5, 'qty_tersisa' => 5]);

    expect(fn () => app(InventoryService::class)->konsumsiStok($barang, 10, 'penjualan'))
        ->toThrow(InsufficientStockException::class);

    expect($batch->fresh()->qty_tersisa)->toBe(5);
    expect($barang->fresh()->stok)->toBe(5);
    expect(StockMovement::count())->toBe(0);
});

test('receiving stock creates a new batch and increments the cached stock column', function () {
    $barang = Barang::factory()->create(['stok' => 0, 'harga_beli' => 5000]);

    $batch = app(InventoryService::class)->terimaBarang($barang, [
        'qty' => 20,
        'tanggal_kedaluwarsa' => now()->addMonth()->toDateString(),
    ]);

    expect($batch->qty_tersisa)->toBe(20);
    expect($batch->harga_beli_satuan)->toBe(5000);
    expect($barang->fresh()->stok)->toBe(20);
    expect(StockMovement::where('reason', 'pembelian')->where('stock_batch_id', $batch->id)->exists())->toBeTrue();
});

test('a sale records which batches were used, for accurate FIFO costing', function () {
    $user = User::factory()->create();
    $barang = Barang::factory()->create(['stok' => 0, 'harga_jual' => 15000]);
    $inventory = app(InventoryService::class);

    $inventory->terimaBarang($barang, ['qty' => 5, 'harga_beli_satuan' => 8000, 'tanggal_terima' => now()->subDays(3)->toDateString()]);
    $inventory->terimaBarang($barang, ['qty' => 5, 'harga_beli_satuan' => 9000, 'tanggal_terima' => now()->subDay()->toDateString()]);

    $response = $this->actingAs($user)->post(route('transaksi.store'), [
        'status' => 'lunas',
        'total_bayar' => 105000,
        'items' => [
            ['barang_id' => $barang->id, 'qty' => 7, 'harga_satuan' => 15000, 'diskon' => 0],
        ],
    ]);

    $response->assertRedirect(route('transaksi.index'));

    $transaksi = Transaksi::first();
    expect($transaksi)->not->toBeNull();
    expect($barang->fresh()->stok)->toBe(3);

    $batches = DetailTransaksiBatch::whereHas('detailTransaksi', fn ($q) => $q->where('transaksi_id', $transaksi->id))->get();
    expect($batches->sum('qty'))->toBe(7);

    expect($batches->sum(fn ($b) => $b->qty * $b->harga_beli_satuan))->toBe(5 * 8000 + 2 * 9000);
});

test('a piutang sale does not require an amount paid and defaults it to zero', function () {
    $user = User::factory()->create();
    $barang = Barang::factory()->create(['stok' => 0, 'harga_jual' => 15000]);
    app(InventoryService::class)->terimaBarang($barang, ['qty' => 5, 'harga_beli_satuan' => 8000]);

    $response = $this->actingAs($user)->post(route('transaksi.store'), [
        'status' => 'piutang',
        'items' => [
            ['barang_id' => $barang->id, 'qty' => 3, 'harga_satuan' => 15000, 'diskon' => 0],
        ],
    ]);

    $response->assertRedirect(route('transaksi.index'));

    $transaksi = Transaksi::first();
    expect($transaksi)->not->toBeNull();
    expect($transaksi->status)->toBe('piutang');
    expect($transaksi->total_bayar)->toBe(0);
    expect($transaksi->kembalian)->toBe(-45000);
});

test('a lunas sale still requires an amount paid', function () {
    $user = User::factory()->create();
    $barang = Barang::factory()->create(['stok' => 0, 'harga_jual' => 15000]);
    app(InventoryService::class)->terimaBarang($barang, ['qty' => 5, 'harga_beli_satuan' => 8000]);

    $response = $this->actingAs($user)->post(route('transaksi.store'), [
        'status' => 'lunas',
        'items' => [
            ['barang_id' => $barang->id, 'qty' => 3, 'harga_satuan' => 15000, 'diskon' => 0],
        ],
    ]);

    $response->assertSessionHasErrors('total_bayar');
    expect(Transaksi::count())->toBe(0);
});

test('selling more than the available stock is rejected with a validation error', function () {
    $user = User::factory()->create();
    $barang = Barang::factory()->create(['stok' => 0]);
    app(InventoryService::class)->terimaBarang($barang, ['qty' => 3]);

    $response = $this->actingAs($user)->post(route('transaksi.store'), [
        'status' => 'lunas',
        'total_bayar' => 100000,
        'items' => [
            ['barang_id' => $barang->id, 'qty' => 5, 'harga_satuan' => 15000, 'diskon' => 0],
        ],
    ]);

    $response->assertSessionHasErrors('items');
    expect(Transaksi::count())->toBe(0);
    expect($barang->fresh()->stok)->toBe(3);
});

test('cancelling a transaction restores stock to the original batches', function () {
    $user = User::factory()->create();
    $barang = Barang::factory()->create(['stok' => 0]);
    $inventory = app(InventoryService::class);
    $batch = $inventory->terimaBarang($barang, ['qty' => 10, 'harga_beli_satuan' => 4000]);

    $this->actingAs($user)->post(route('transaksi.store'), [
        'status' => 'lunas',
        'total_bayar' => 60000,
        'items' => [
            ['barang_id' => $barang->id, 'qty' => 6, 'harga_satuan' => 10000, 'diskon' => 0],
        ],
    ]);

    expect($barang->fresh()->stok)->toBe(4);
    expect($batch->fresh()->qty_tersisa)->toBe(4);

    $transaksi = Transaksi::first();
    $this->actingAs($user)->patch(route('transaksi.status', $transaksi), ['status' => 'batal']);

    expect($barang->fresh()->stok)->toBe(10);
    expect($batch->fresh()->qty_tersisa)->toBe(10);
    expect(StockMovement::where('reason', 'pembatalan')->exists())->toBeTrue();
});
