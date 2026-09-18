<?php

use App\Models\Barang;
use App\Models\StockBatch;
use App\Models\User;

test('expiry scopes correctly classify expired, soon, and safe batches', function () {
    $barang = Barang::factory()->create();

    $sudahKedaluwarsa = StockBatch::factory()->for($barang)->create([
        'tanggal_kedaluwarsa' => now()->subDay()->toDateString(),
    ]);
    $akanKedaluwarsa = StockBatch::factory()->for($barang)->create([
        'tanggal_kedaluwarsa' => now()->addDays(3)->toDateString(),
    ]);
    $aman = StockBatch::factory()->for($barang)->create([
        'tanggal_kedaluwarsa' => now()->addDays(30)->toDateString(),
    ]);
    $tanpaTanggal = StockBatch::factory()->for($barang)->create([
        'tanggal_kedaluwarsa' => null,
    ]);

    $idSudahKedaluwarsa = StockBatch::sudahKedaluwarsa()->pluck('id');
    expect($idSudahKedaluwarsa)->toContain($sudahKedaluwarsa->id);
    expect($idSudahKedaluwarsa)->not->toContain($akanKedaluwarsa->id);
    expect($idSudahKedaluwarsa)->not->toContain($aman->id);
    expect($idSudahKedaluwarsa)->not->toContain($tanpaTanggal->id);

    $idAkanKedaluwarsa = StockBatch::akanKedaluwarsa(7)->pluck('id');
    expect($idAkanKedaluwarsa)->toContain($akanKedaluwarsa->id);
    expect($idAkanKedaluwarsa)->not->toContain($sudahKedaluwarsa->id);
    expect($idAkanKedaluwarsa)->not->toContain($aman->id);

    expect($sudahKedaluwarsa->status_kedaluwarsa)->toBe('kedaluwarsa');
    expect($akanKedaluwarsa->status_kedaluwarsa)->toBe('segera');
    expect($aman->status_kedaluwarsa)->toBe('aman');
    expect($tanpaTanggal->status_kedaluwarsa)->toBe('tidak_ada');
});

test('a batch with zero remaining stock is excluded from expiry alerts', function () {
    $barang = Barang::factory()->create();
    StockBatch::factory()->for($barang)->create([
        'tanggal_kedaluwarsa' => now()->subDay()->toDateString(),
        'qty_tersisa' => 0,
    ]);

    expect(StockBatch::aktif()->sudahKedaluwarsa()->count())->toBe(0);
});

test('dashboard shows the expiry alert badge and lists affected batches', function () {
    $user = User::factory()->create();
    $barang = Barang::factory()->create(['nama_barang' => 'Roti Tawar']);
    StockBatch::factory()->for($barang)->create([
        'tanggal_kedaluwarsa' => now()->subDay()->toDateString(),
        'qty_tersisa' => 4,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Roti Tawar');
    $response->assertSee('kedaluwarsa');
});

test('the expiry check command lists expired and soon-to-expire batches', function () {
    $barang = Barang::factory()->create(['nama_barang' => 'Susu UHT']);
    StockBatch::factory()->for($barang)->create([
        'tanggal_kedaluwarsa' => now()->subDay()->toDateString(),
        'qty_tersisa' => 2,
    ]);

    $this->artisan('barang:cek-kedaluwarsa')
        ->assertExitCode(0)
        ->expectsOutputToContain('Susu UHT');
});
