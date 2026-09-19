<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class BarangController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    public function index(Request $request)
    {
        $query = Barang::query();

        if ($request->get('filter') === 'low_stock') {
            $query->whereColumn('stok', '<=', 'stok_minimum');
        }
        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($q2) use ($q) {
                $q2->where('nama_barang', 'like', "%{$q}%")
                    ->orWhere('kode_barang', 'like', "%{$q}%");
            });
        }

        $barang = $query->orderBy('nama_barang')->paginate(20)->withQueryString();
        $kategori = Barang::select('kategori')->distinct()->whereNotNull('kategori')->pluck('kategori');
        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('barang.index', compact('barang', 'kategori', 'stockAlertCount'))
            ->with('title', 'Data Barang');
    }

    public function create()
    {
        $kategori = Barang::select('kategori')->distinct()->whereNotNull('kategori')->pluck('kategori');
        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('barang.create', compact('kategori', 'stockAlertCount'))
            ->with('title', 'Tambah Barang');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_barang' => 'required|string|max:20|unique:barangs',
            'nama_barang' => 'required|string|max:150',
            'kategori' => 'nullable|string|max:60',
            'satuan' => 'required|string|max:20',
            'harga_beli' => 'required|integer|min:0',
            'harga_jual' => 'required|integer|min:0',
            'stok' => 'required|integer|min:0',
            'stok_minimum' => 'required|integer|min:0',
            'deskripsi' => 'nullable|string',
            'tanggal_kedaluwarsa' => 'nullable|date',
        ]);

        $stokAwal = $validated['stok'];
        $tanggalKedaluwarsa = $validated['tanggal_kedaluwarsa'] ?? null;
        unset($validated['stok'], $validated['tanggal_kedaluwarsa']);
        $validated['stok'] = 0;

        $barang = Barang::create($validated);

        if ($stokAwal > 0) {
            $this->inventory->terimaBarang($barang, [
                'qty' => $stokAwal,
                'harga_beli_satuan' => $barang->harga_beli,
                'tanggal_kedaluwarsa' => $tanggalKedaluwarsa,
                'reason' => 'stok_awal',
                'catatan' => 'Stok awal saat barang dibuat',
            ]);
        }

        return redirect()->route('barang.index')
            ->with('success', "Barang \"{$barang->nama_barang}\" berhasil ditambahkan.");
    }

    public function show(Barang $barang)
    {
        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();
        $batches = $barang->stockBatches()->aktif()->urutanFifo()->get();
        $batchKedaluwarsa = $barang->stockBatches()->aktif()->sudahKedaluwarsa()->count();
        $batchAkanKedaluwarsa = $barang->stockBatches()->aktif()->akanKedaluwarsa()->count();

        return view('barang.show', compact('barang', 'stockAlertCount', 'batches', 'batchKedaluwarsa', 'batchAkanKedaluwarsa'))
            ->with('title', $barang->nama_barang);
    }

    public function edit(Barang $barang)
    {
        $kategori = Barang::select('kategori')->distinct()->whereNotNull('kategori')->pluck('kategori');
        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('barang.edit', compact('barang', 'kategori', 'stockAlertCount'))
            ->with('title', 'Edit Barang');
    }

    public function stockIn(Barang $barang)
    {
        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('barang.stock-in', compact('barang', 'stockAlertCount'))
            ->with('title', 'Tambah Stok');
    }

    public function storeStockIn(Request $request, Barang $barang)
    {
        $validated = $request->validate([
            'qty' => 'required|integer|min:1',
            'harga_beli_satuan' => 'nullable|integer|min:0',
            'tanggal_terima' => 'nullable|date',
            'tanggal_kedaluwarsa' => 'nullable|date|after_or_equal:tanggal_terima',
            'supplier' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $this->inventory->terimaBarang($barang, [
            'qty' => $validated['qty'],
            'harga_beli_satuan' => $validated['harga_beli_satuan'] ?? $barang->harga_beli,
            'tanggal_terima' => $validated['tanggal_terima'] ?? now()->toDateString(),
            'tanggal_kedaluwarsa' => $validated['tanggal_kedaluwarsa'] ?? null,
            'supplier' => $validated['supplier'] ?? null,
            'catatan' => $validated['notes'] ?? 'Stok masuk manual',
        ]);

        return redirect()->route('barang.show', $barang)
            ->with('success', "Stok \"{$barang->nama_barang}\" berhasil ditambahkan.");
    }

    public function update(Request $request, Barang $barang)
    {
        $validated = $request->validate([
            'kode_barang' => 'required|string|max:20|unique:barangs,kode_barang,'.$barang->id,
            'nama_barang' => 'required|string|max:150',
            'kategori' => 'nullable|string|max:60',
            'satuan' => 'required|string|max:20',
            'harga_beli' => 'required|integer|min:0',
            'harga_jual' => 'required|integer|min:0',
            'stok_minimum' => 'required|integer|min:0',
            'deskripsi' => 'nullable|string',
        ]);

        $barang->update($validated);

        return redirect()->route('barang.index')
            ->with('success', "Barang \"{$barang->nama_barang}\" berhasil diperbarui.");
    }

    public function destroy(Barang $barang)
    {
        if ($barang->detailTransaksi()->exists()) {
            return back()->with('error', "Barang \"{$barang->nama_barang}\" tidak dapat dihapus karena sudah ada dalam transaksi.");
        }
        $nama = $barang->nama_barang;
        $barang->delete();

        return redirect()->route('barang.index')
            ->with('success', "Barang \"{$nama}\" berhasil dihapus.");
    }
}
