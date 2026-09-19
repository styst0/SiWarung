<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockImportController extends Controller
{
    private const KOLOM_WAJIB = ['kode_barang', 'tanggal_terima', 'qty', 'harga_beli_satuan'];

    private const KOLOM_OPSIONAL = ['tanggal_kedaluwarsa', 'supplier', 'catatan'];

    public function __construct(protected InventoryService $inventory) {}

    public function create()
    {
        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('stok.import', compact('stockAlertCount'))
            ->with('title', 'Import Stok Masuk');
    }

    public function template()
    {
        $headers = array_merge(self::KOLOM_WAJIB, self::KOLOM_OPSIONAL);
        $contoh = [
            'SBK-001',
            now()->toDateString(),
            '24',
            '26000',
            now()->addMonths(6)->toDateString(),
            'Distributor Sembako Jaya',
            'Contoh baris - hapus/ganti dengan data dari nota',
        ];

        $csv = implode(',', $headers)."\n".implode(',', array_map(
            fn (string $value) => str_contains($value, ',') ? '"'.$value.'"' : $value,
            $contoh
        ))."\n";

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

        DB::transaction(function () use ($validRows) {
            foreach ($validRows as $row) {
                $this->inventory->terimaBarang($row['barang'], [
                    'qty' => $row['qty'],
                    'harga_beli_satuan' => $row['harga_beli_satuan'],
                    'tanggal_terima' => $row['tanggal_terima'],
                    'tanggal_kedaluwarsa' => $row['tanggal_kedaluwarsa'],
                    'supplier' => $row['supplier'],
                    'catatan' => $row['catatan'] ?? 'Import massal dari nota distributor',
                ]);
            }
        });

        return redirect()->route('stok-import.create')
            ->with('success', count($validRows).' batch stok masuk berhasil diimport.');
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

            $kodeBarang = trim((string) ($data['kode_barang'] ?? ''));
            $barang = null;

            if ($kodeBarang === '') {
                $pesan[] = 'kode_barang kosong';
            } else {
                if (! array_key_exists($kodeBarang, $barangCache)) {
                    $barangCache[$kodeBarang] = Barang::where('kode_barang', $kodeBarang)->first();
                }
                $barang = $barangCache[$kodeBarang];

                if (! $barang) {
                    $pesan[] = "kode_barang \"{$kodeBarang}\" tidak ditemukan di sistem";
                }
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

            if ($pesan !== []) {
                $errors[] = ['baris' => $nomorBaris, 'kode_barang' => $kodeBarang, 'pesan' => $pesan];

                continue;
            }

            $validRows[] = [
                'barang' => $barang,
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
