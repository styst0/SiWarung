<?php

use App\Models\Barang;
use App\Models\StockBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function buatCsv(string $isi): UploadedFile
{
    return UploadedFile::fake()->createWithContent('import.csv', $isi);
}

test('import CSV yang valid membuat batch stok masuk untuk setiap baris', function () {
    $user = User::factory()->create();
    $barangA = Barang::factory()->create(['kode_barang' => 'IMP-001', 'stok' => 0]);
    $barangB = Barang::factory()->create(['kode_barang' => 'IMP-002', 'stok' => 0]);

    $csv = "kode_barang,tanggal_terima,qty,harga_beli_satuan,tanggal_kedaluwarsa,supplier,catatan\n"
        .'IMP-001,'.now()->subDay()->toDateString().',10,5000,'.now()->addMonths(3)->toDateString().",Distributor A,\n"
        .'IMP-002,'.now()->subDay()->toDateString().",25,3000,,,\n";

    $response = $this->actingAs($user)->post(route('stok-import.store'), [
        'file' => buatCsv($csv),
    ]);

    $response->assertRedirect(route('stok-import.create'));
    $response->assertSessionHas('success');
    $response->assertSessionDoesntHaveErrors();

    expect($barangA->fresh()->stok)->toBe(10);
    expect($barangB->fresh()->stok)->toBe(25);
    expect(StockBatch::where('barang_id', $barangA->id)->count())->toBe(1);
    expect(StockBatch::where('barang_id', $barangB->id)->count())->toBe(1);
});

test('import ditolak semua (all-or-nothing) kalau ada satu baris yang bermasalah', function () {
    $user = User::factory()->create();
    $barangA = Barang::factory()->create(['kode_barang' => 'IMP-010', 'stok' => 0]);

    $csv = "kode_barang,tanggal_terima,qty,harga_beli_satuan\n"
        .'IMP-010,'.now()->subDay()->toDateString().",10,5000\n"
        .'KODE-TIDAK-ADA,'.now()->subDay()->toDateString().",5,2000\n";

    $response = $this->actingAs($user)->post(route('stok-import.store'), [
        'file' => buatCsv($csv),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('importErrors');

    // Tidak ada satu pun batch yang dibuat, termasuk untuk baris yang
    // sebenarnya valid (IMP-010) — import bersifat semua-atau-tidak.
    expect($barangA->fresh()->stok)->toBe(0);
    expect(StockBatch::count())->toBe(0);
});

test('import ditolak kalau kolom wajib tidak ada di file', function () {
    $user = User::factory()->create();

    $csv = "kode_barang,qty\nIMP-001,10\n";

    $response = $this->actingAs($user)->post(route('stok-import.store'), [
        'file' => buatCsv($csv),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(StockBatch::count())->toBe(0);
});

test('template CSV bisa diunduh dan berisi header yang benar', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('stok-import.template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->getContent())->toContain('kode_barang,tanggal_terima,qty,harga_beli_satuan');
});
