<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Barang;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockMovementController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    public function index(Request $request)
    {
        $query = StockMovement::with('barang')->latest();

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('barang')) {
            $query->whereHas('barang', function ($query) use ($request) {
                $query->where('nama_barang', 'like', "%{$request->barang}%")
                    ->orWhere('kode_barang', 'like', "%{$request->barang}%");
            });
        }

        if ($request->filled('dari')) {
            $query->whereDate('created_at', '>=', $request->dari);
        }

        if ($request->filled('sampai')) {
            $query->whereDate('created_at', '<=', $request->sampai);
        }

        $stockMovements = $query->paginate(15)->withQueryString();
        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('stock-movements.index', compact('stockMovements', 'stockAlertCount'))
            ->with('title', 'Manajemen Stok');
    }

    public function create()
    {
        $barangs = Barang::orderBy('nama_barang')->get();
        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('stock-movements.create', compact('barangs', 'stockAlertCount'))
            ->with('title', 'Tambah Stok');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'barang_id' => ['required', 'exists:barangs,id'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'qty' => ['required', 'integer', 'min:1'],
            'tanggal_kedaluwarsa' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $barang = Barang::findOrFail($validated['barang_id']);

        if ($validated['direction'] === 'in') {
            $this->inventory->terimaBarang($barang, [
                'qty' => $validated['qty'],
                'harga_beli_satuan' => $barang->harga_beli,
                'tanggal_kedaluwarsa' => $validated['tanggal_kedaluwarsa'] ?? null,
                'reason' => 'penyesuaian',
                'catatan' => $validated['notes'] ?? 'Penyesuaian stok manual',
            ]);
        } else {
            try {
                $this->inventory->konsumsiStok($barang, $validated['qty'], 'penyesuaian', null, $validated['notes'] ?? 'Penyesuaian stok manual');
            } catch (InsufficientStockException $e) {
                throw ValidationException::withMessages(['qty' => $e->getMessage()]);
            }
        }

        return redirect()->route('stock-movements.index')
            ->with('success', "Stok \"{$barang->nama_barang}\" berhasil diperbarui.");
    }
}
