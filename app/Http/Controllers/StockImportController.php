<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Services\HargaJualService;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockImportController extends Controller
{
    private const KOLOM_WAJIB = ['kode_barang', 'tanggal_terima', 'qty', 'harga_beli_satuan'];

    private const KOLOM_OPSIONAL = ['tanggal_kedaluwarsa', 'supplier', 'catatan'];

    private const KOLOM_BARANG_BARU = ['nama_barang', 'kategori', 'satuan', 'harga_jual', 'stok_minimum'];

    public function __construct(protected InventoryService $inventory, protected HargaJualService $hargaJual) {}

    public function create()
    {
        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('stok.import', compact('stockAlertCount'))
            ->with('title', 'Import Stok Masuk');
    }

    public function template()
    {
        $headers = array_merge(self::KOLOM_WAJIB, self::KOLOM_OPSIONAL, self::KOLOM_BARANG_BARU);

        $contohLama = [
            'SBK-001',
            now()->toDateString(),
            '24',
            '26000',
            now()->addMonths(6)->toDateString(),
            'Distributor Sembako Jaya',
            'Contoh baris untuk barang yang SUDAH ada di sistem - hapus/ganti',
            '', '', '', '', '',
        ];

        $contohBaru = [
            'SBK-999',
            now()->toDateString(),
            '12',
            '15000',
            now()->addMonths(6)->toDateString(),
            'Distributor Sembako Jaya',
            'Contoh baris untuk barang BARU (belum terdaftar) - hapus/ganti',
            'Nama Barang Baru',
            'Sembako',
            'pcs',
            '18000',
            '10',
        ];

        $tulisBaris = fn (array $baris) => implode(',', array_map(
            fn (string $value) => str_contains($value, ',') ? '"'.$value.'"' : $value,
            $baris
        ));

        $csv = implode(',', $headers)."\n".$tulisBaris($contohLama)."\n".$tulisBaris($contohBaru)."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_import_stok_masuk.csv"',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return back()->with('error', 'File tidak bisa dibaca.');
        }

        $headerRow = fgetcsv($handle);

        if ($headerRow === false) {
            fclose($handle);

            return back()->with('error', 'File CSV kosong atau tidak bisa dibaca.');
        }

        $headers = array_map(fn (?string $h) => strtolower(trim((string) $h)), $headerRow);
        $kolomHilang = array_diff(self::KOLOM_WAJIB, $headers);

        if ($kolomHilang !== []) {
            fclose($handle);

            return back()->with('error', 'Kolom wajib tidak ditemukan di file: '.implode(', ', $kolomHilang).'. Gunakan template yang disediakan.');
        }

        $baris = [];
        $nomorBaris = 1;
        while (($raw = fgetcsv($handle)) !== false) {
            $nomorBaris++;

            if (count(array_filter($raw, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $data = array_combine($headers, array_pad($raw, count($headers), null));
            $baris[] = ['baris' => $nomorBaris, 'data' => $data];
        }
        fclose($handle);

        if ($baris === []) {
            return back()->with('error', 'Tidak ada baris data di file (selain header).');
        }

        [$errors, $validRows] = $this->validasiSemuaBaris($baris);

        if ($errors !== []) {
            return back()->with('importErrors', $errors)->with('error',
                count($errors).' dari '.count($baris).' baris bermasalah. Tidak ada yang diimport — perbaiki file lalu upload ulang.'
            );
        }

        $barangBaruDibuat = [];

        DB::transaction(function () use ($validRows, &$barangBaruDibuat) {
            foreach ($validRows as $row) {
                $ref = $row['barang_ref'];

                if ($ref['type'] === 'baru') {
                    $kode = $ref['attrs']['kode_barang'];

                    if (! isset($barangBaruDibuat[$kode])) {
                        $attrs = $ref['attrs'];

                        $barangBaruDibuat[$kode] = Barang::create([
                            'kode_barang' => $attrs['kode_barang'],
                            'nama_barang' => $attrs['nama_barang'],
                            'kategori' => $attrs['kategori'],
                            'satuan' => $attrs['satuan'],
                            'harga_beli' => $row['harga_beli_satuan'],
                            'harga_jual' => $attrs['harga_jual'] ?? $this->hargaJual->hitungOtomatis($row['harga_beli_satuan'], $attrs['satuan']),
                            'stok' => 0,
                            'stok_minimum' => $attrs['stok_minimum'],
                        ]);
                    }

                    $barang = $barangBaruDibuat[$kode];
                } else {
                    $barang = $ref['barang'];
                }

                $this->inventory->terimaBarang($barang, [
                    'qty' => $row['qty'],
                    'harga_beli_satuan' => $row['harga_beli_satuan'],
                    'tanggal_terima' => $row['tanggal_terima'],
                    'tanggal_kedaluwarsa' => $row['tanggal_kedaluwarsa'],
                    'supplier' => $row['supplier'],
                    'catatan' => $row['catatan'] ?? 'Import massal dari nota distributor',
                ]);
            }
        });

        $jumlahBarangBaru = count($barangBaruDibuat);
        $pesanSukses = count($validRows).' batch stok masuk berhasil diimport';
        $pesanSukses .= $jumlahBarangBaru > 0 ? " ({$jumlahBarangBaru} barang baru otomatis terdaftar)." : '.';

        return redirect()->route('stok-import.create')->with('success', $pesanSukses);
    }

    private function validasiSemuaBaris(array $baris): array
    {
        $errors = [];
        $validRows = [];
        $barangCache = [];

        foreach ($baris as $entry) {
            $nomorBaris = $entry['baris'];
            $data = $entry['data'];
            $pesan = [];
            $barangRef = null;

            $kodeBarang = trim((string) ($data['kode_barang'] ?? ''));

            if ($kodeBarang === '') {
                $pesan[] = 'kode_barang kosong';
            } else {
                if (! array_key_exists($kodeBarang, $barangCache)) {
                    $resolusi = $this->resolveBarangBaris($kodeBarang, $data);
                    $barangCache[$kodeBarang] = $resolusi['ref'];

                    if ($resolusi['pesan'] !== []) {
                        // pesan spesifik untuk baris yang PERTAMA kali mengenalkan kode ini
                        $pesan = array_merge($pesan, $resolusi['pesan']);
                    }
                } elseif ($barangCache[$kodeBarang] === null) {
                    $pesan[] = "kode_barang \"{$kodeBarang}\" belum terdaftar dan gagal didaftarkan otomatis (lihat baris pertama yang memakai kode ini pada file ini)";
                }

                $barangRef = $barangCache[$kodeBarang];
            }

            $tanggalTerima = $this->parseTanggal($data['tanggal_terima'] ?? null);
            if (! $tanggalTerima) {
                $pesan[] = 'tanggal_terima tidak valid (format: YYYY-MM-DD)';
            } elseif ($tanggalTerima->isFuture()) {
                $pesan[] = 'tanggal_terima tidak boleh di masa depan';
            }

            $qty = filter_var(trim((string) ($data['qty'] ?? '')), FILTER_VALIDATE_INT);
            if ($qty === false || $qty < 1) {
                $pesan[] = 'qty harus angka bulat minimal 1';
            }

            $harga = filter_var(trim((string) ($data['harga_beli_satuan'] ?? '')), FILTER_VALIDATE_INT);
            if ($harga === false || $harga < 0) {
                $pesan[] = 'harga_beli_satuan harus angka bulat, minimal 0';
            }

            $tanggalKedaluwarsa = null;
            $rawKedaluwarsa = trim((string) ($data['tanggal_kedaluwarsa'] ?? ''));
            if ($rawKedaluwarsa !== '') {
                $tanggalKedaluwarsa = $this->parseTanggal($rawKedaluwarsa);
                if (! $tanggalKedaluwarsa) {
                    $pesan[] = 'tanggal_kedaluwarsa tidak valid (format: YYYY-MM-DD)';
                } elseif ($tanggalTerima && $tanggalKedaluwarsa->lt($tanggalTerima)) {
                    $pesan[] = 'tanggal_kedaluwarsa tidak boleh sebelum tanggal_terima';
                }
            }

            if ($pesan !== [] || $barangRef === null) {
                if ($pesan === []) {
                    $pesan[] = "kode_barang \"{$kodeBarang}\" tidak valid";
                }
                $errors[] = ['baris' => $nomorBaris, 'kode_barang' => $kodeBarang, 'pesan' => $pesan];

                continue;
            }

            $validRows[] = [
                'barang_ref' => $barangRef,
                'tanggal_terima' => $tanggalTerima->toDateString(),
                'qty' => $qty,
                'harga_beli_satuan' => $harga,
                'tanggal_kedaluwarsa' => $tanggalKedaluwarsa?->toDateString(),
                'supplier' => trim((string) ($data['supplier'] ?? '')) ?: null,
                'catatan' => trim((string) ($data['catatan'] ?? '')) ?: null,
            ];
        }

        return [$errors, $validRows];
    }

    /**
     * Mencocokkan kode_barang ke barang yang sudah ada, atau menyiapkan data
     * untuk mendaftarkan barang baru kalau kode_barang belum dikenal.
     *
     * @return array{ref: array{type:string,barang?:Barang,attrs?:array}|null, pesan: array<int,string>}
     */
    private function resolveBarangBaris(string $kodeBarang, array $data): array
    {
        $existing = Barang::where('kode_barang', $kodeBarang)->first();

        if ($existing) {
            return ['ref' => ['type' => 'ada', 'barang' => $existing], 'pesan' => []];
        }

        $pesan = [];
        $namaBarang = trim((string) ($data['nama_barang'] ?? ''));

        if ($namaBarang === '') {
            return ['ref' => null, 'pesan' => [
                "kode_barang \"{$kodeBarang}\" belum terdaftar dan nama_barang tidak diisi untuk mendaftarkannya sebagai barang baru",
            ]];
        }

        if (mb_strlen($namaBarang) > 150) {
            $pesan[] = 'nama_barang maksimal 150 karakter';
        }

        if (mb_strlen($kodeBarang) > 20) {
            $pesan[] = 'kode_barang maksimal 20 karakter';
        }

        $kategori = trim((string) ($data['kategori'] ?? '')) ?: null;
        if ($kategori !== null && mb_strlen($kategori) > 60) {
            $pesan[] = 'kategori maksimal 60 karakter';
        }

        $satuan = trim((string) ($data['satuan'] ?? '')) ?: 'pcs';
        if (mb_strlen($satuan) > 20) {
            $pesan[] = 'satuan maksimal 20 karakter';
        }

        $hargaJual = null;
        $hargaJualRaw = trim((string) ($data['harga_jual'] ?? ''));
        if ($hargaJualRaw !== '') {
            $hargaJual = filter_var($hargaJualRaw, FILTER_VALIDATE_INT);
            if ($hargaJual === false || $hargaJual < 0) {
                $pesan[] = 'harga_jual harus angka bulat, minimal 0';
                $hargaJual = null;
            }
        }

        $stokMinimum = 5;
        $stokMinimumRaw = trim((string) ($data['stok_minimum'] ?? ''));
        if ($stokMinimumRaw !== '') {
            $stokMinimum = filter_var($stokMinimumRaw, FILTER_VALIDATE_INT);
            if ($stokMinimum === false || $stokMinimum < 0) {
                $pesan[] = 'stok_minimum harus angka bulat, minimal 0';
                $stokMinimum = 5;
            }
        }

        if ($pesan !== []) {
            return ['ref' => null, 'pesan' => $pesan];
        }

        return [
            'ref' => [
                'type' => 'baru',
                'attrs' => [
                    'kode_barang' => $kodeBarang,
                    'nama_barang' => $namaBarang,
                    'kategori' => $kategori,
                    'satuan' => $satuan,
                    'stok_minimum' => $stokMinimum,
                    'harga_jual' => $hargaJual,
                ],
            ],
            'pesan' => [],
        ];
    }

    private function parseTanggal(?string $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            $date = Carbon::createFromFormat('!'.$format, $value);
            if ($date !== false) {
                return $date;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
