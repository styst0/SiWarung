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

test('import CSV bisa mendaftarkan barang baru otomatis kalau kode_barang belum ada dan nama_barang diisi', function () {
    $user = User::factory()->create();

    expect(Barang::where('kode_barang', 'NEW-001')->exists())->toBeFalse();

    $csv = "kode_barang,tanggal_terima,qty,harga_beli_satuan,tanggal_kedaluwarsa,supplier,catatan,nama_barang,kategori,satuan,harga_jual,stok_minimum\n"
        .'NEW-001,'.now()->subDay()->toDateString().',15,12000,,Distributor Baru,,Kecap Manis 600ml,Bumbu Dapur,botol,15000,8'."\n";

    $response = $this->actingAs($user)->post(route('stok-import.store'), [
        'file' => buatCsv($csv),
    ]);

    $response->assertRedirect(route('stok-import.create'));
    $response->assertSessionHas('success');
    $response->assertSessionDoesntHaveErrors();

    $barang = Barang::where('kode_barang', 'NEW-001')->first();
    expect($barang)->not->toBeNull();
    expect($barang->nama_barang)->toBe('Kecap Manis 600ml');
    expect($barang->kategori)->toBe('Bumbu Dapur');
    expect($barang->satuan)->toBe('botol');
    expect($barang->harga_beli)->toBe(12000);
    expect($barang->harga_jual)->toBe(15000);
    expect($barang->stok_minimum)->toBe(8);
    expect($barang->stok)->toBe(15);
    expect(StockBatch::where('barang_id', $barang->id)->count())->toBe(1);
});

test('import CSV ditolak semua kalau barang baru tidak diisi nama_barang', function () {
    $user = User::factory()->create();

    $csv = "kode_barang,tanggal_terima,qty,harga_beli_satuan,nama_barang\n"
        .'NEW-002,'.now()->subDay()->toDateString().",5,10000,\n";

    $response = $this->actingAs($user)->post(route('stok-import.store'), [
        'file' => buatCsv($csv),
    ]);

    $response->assertSessionHas('importErrors');
    expect(Barang::where('kode_barang', 'NEW-002')->exists())->toBeFalse();
    expect(StockBatch::count())->toBe(0);
});

test('import CSV barang baru tanpa harga_jual dihitung otomatis pakai markup satuan lalu dibulatkan genap', function () {
    $user = User::factory()->create();

    // pcs: markup 200-500. 9000+200=9200 -> genap 500 berikutnya = 9500 (<= 9000+500=9500).
    $csv = "kode_barang,tanggal_terima,qty,harga_beli_satuan,nama_barang,satuan\n"
        .'NEW-003,'.now()->subDay()->toDateString().",5,9000,Sabun Cuci Baru,pcs\n";

    $response = $this->actingAs($user)->post(route('stok-import.store'), [
        'file' => buatCsv($csv),
    ]);

    $response->assertSessionHas('success');
    $barang = Barang::where('kode_barang', 'NEW-003')->first();
    expect($barang->harga_jual)->toBe(9500);
    expect($barang->stok_minimum)->toBe(5);
    expect($barang->kategori)->toBeNull();
});

test('import CSV barang baru dengan satuan berbeda dapat markup otomatis yang berbeda pula', function () {
    $user = User::factory()->create();

    // dus: markup 2000-3500. 61262+2000=63262 -> genap 500 berikutnya = 63500.
    $csv = "kode_barang,tanggal_terima,qty,harga_beli_satuan,nama_barang,satuan\n"
        .'NEW-006,'.now()->subDay()->toDateString().",3,61262,Krim Kental Manis Dus,dus\n";

    $response = $this->actingAs($user)->post(route('stok-import.store'), [
        'file' => buatCsv($csv),
    ]);

    $response->assertSessionHas('success');
    $barang = Barang::where('kode_barang', 'NEW-006')->first();
    expect($barang->harga_jual)->toBe(63500);
});

test('import CSV barang baru dengan harga_jual diisi manual tidak ikut dibulatkan otomatis', function () {
    $user = User::factory()->create();

    $csv = "kode_barang,tanggal_terima,qty,harga_beli_satuan,nama_barang,satuan,harga_jual\n"
        .'NEW-007,'.now()->subDay()->toDateString().",4,12000,Teh Kotak Manual,pcs,13370\n";

    $response = $this->actingAs($user)->post(route('stok-import.store'), [
        'file' => buatCsv($csv),
    ]);

    $response->assertSessionHas('success');
    $barang = Barang::where('kode_barang', 'NEW-007')->first();
    // Harga manual dipakai apa adanya, TIDAK dibulatkan ke kelipatan 500.
    expect($barang->harga_jual)->toBe(13370);
});

test('import CSV dengan dua baris kode_barang baru yang sama hanya membuat satu barang tapi dua batch stok', function () {
    $user = User::factory()->create();

    $csv = "kode_barang,tanggal_terima,qty,harga_beli_satuan,tanggal_kedaluwarsa,nama_barang,satuan,harga_jual\n"
        .'NEW-004,'.now()->subDays(2)->toDateString().','.'10,8000,'.now()->addMonths(2)->toDateString().',Roti Tawar,pcs,10000'."\n"
        .'NEW-004,'.now()->subDay()->toDateString().','.'20,8200,'.now()->addMonths(3)->toDateString().",,,\n";

    $response = $this->actingAs($user)->post(route('stok-import.store'), [
        'file' => buatCsv($csv),
    ]);

    $response->assertSessionHas('success');
    expect(Barang::where('kode_barang', 'NEW-004')->count())->toBe(1);
    $barang = Barang::where('kode_barang', 'NEW-004')->first();
    expect($barang->stok)->toBe(30);
    expect(StockBatch::where('barang_id', $barang->id)->count())->toBe(2);
});

test('import CSV campur barang lama dan barang baru dalam satu file berhasil sekaligus', function () {
    $user = User::factory()->create();
    $barangLama = Barang::factory()->create(['kode_barang' => 'LAMA-001', 'stok' => 0]);

    $csv = "kode_barang,tanggal_terima,qty,harga_beli_satuan,nama_barang,satuan,harga_jual\n"
        .'LAMA-001,'.now()->subDay()->toDateString().",10,5000,,,\n"
        .'NEW-005,'.now()->subDay()->toDateString().",7,6000,Minuman Baru,botol,8000\n";

    $response = $this->actingAs($user)->post(route('stok-import.store'), [
        'file' => buatCsv($csv),
    ]);

    $response->assertSessionHas('success');
    expect($barangLama->fresh()->stok)->toBe(10);
    $barangBaru = Barang::where('kode_barang', 'NEW-005')->first();
    expect($barangBaru)->not->toBeNull();
    expect($barangBaru->stok)->toBe(7);
});

test('halaman import stok bisa diakses dan menampilkan instruksi barang baru', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('stok-import.create'));

    $response->assertOk();
    $response->assertSee('otomatis didaftarkan');
});
