<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Barang;
use App\Models\StockBatch;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockBatchController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    public function kedaluwarsa()
    {
        $batchSudahKedaluwarsa = StockBatch::with('barang')
            ->aktif()->sudahKedaluwarsa()
            ->orderBy('tanggal_kedaluwarsa')
            ->paginate(12, ['*'], 'sudah_page')
            ->withQueryString();

        $batchAkanKedaluwarsa = StockBatch::with('barang')
            ->aktif()->akanKedaluwarsa()
            ->orderBy('tanggal_kedaluwarsa')
            ->paginate(12, ['*'], 'akan_page')
            ->withQueryString();

        return view('barang-kedaluwarsa.index', compact(
            'batchSudahKedaluwarsa',
            'batchAkanKedaluwarsa',
        ))->with('title', 'Barang Kedaluwarsa');
    }

    public function updateTanggalKedaluwarsa(Request $request, Barang $barang, StockBatch $batch)
    {
        abort_unless($batch->barang_id === $barang->id, 404);

        $validated = $request->validate([
            'tanggal_kedaluwarsa' => ['nullable', 'date'],
        ]);

        $batch->update(['tanggal_kedaluwarsa' => $validated['tanggal_kedaluwarsa']]);

        $pesan = $validated['tanggal_kedaluwarsa']
            ? "Tanggal kedaluwarsa batch \"{$batch->kode_batch}\" berhasil disimpan."
            : "Tanggal kedaluwarsa batch \"{$batch->kode_batch}\" dikosongkan.";

        return redirect()->route('barang.show', $barang)->with('success', $pesan);
    }

    public function penyusutan(Request $request, Barang $barang, StockBatch $batch)
    {
        abort_unless($batch->barang_id === $barang->id, 404);

        if ($batch->qty_tersisa <= 0) {
            return back()->with('error', "Batch \"{$batch->kode_batch}\" sudah tidak memiliki sisa stok.");
        }

        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:'.$batch->qty_tersisa],
            'alasan' => ['required', Rule::in(['kedaluwarsa', 'rusak', 'hilang', 'lainnya'])],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $labelAlasan = [
            'kedaluwarsa' => 'Kedaluwarsa',
            'rusak' => 'Rusak',
            'hilang' => 'Hilang',
            'lainnya' => 'Lainnya',
        ][$validated['alasan']];

        try {
            $this->inventory->catatPenyusutan($batch, (int) $validated['qty'], $labelAlasan, $validated['catatan'] ?? null);
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('barang.show', $barang)
            ->with('success', "Penyusutan batch \"{$batch->kode_batch}\" berhasil dicatat.");
    }
}
