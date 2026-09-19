<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Barang;
use App\Models\DetailTransaksi;
use App\Models\DetailTransaksiBatch;
use App\Models\Transaksi;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransaksiController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    public function index(Request $request)
    {
        $query = Transaksi::with('user')->latest();

        if ($request->filled('dari')) {
            $query->whereDate('created_at', '>=', $request->dari);
        }
        if ($request->filled('sampai')) {
            $query->whereDate('created_at', '<=', $request->sampai);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($q2) use ($q) {
                $q2->where('pelanggan', 'like', "%{$q}%")
                    ->orWhere('kode_transaksi', 'like', "%{$q}%");
            });
        }

        $totalHariIni = Transaksi::whereDate('created_at', today())->count();
        $pendapatanHariIni = Transaksi::whereDate('created_at', today())->where('status', 'lunas')->sum('total_harga');
        $piutangBelumLunas = Transaksi::where('status', 'piutang')->sum('total_harga');
        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();
        $transaksi = $query->paginate(15)->withQueryString();

        return view('transaksi.index', compact(
            'transaksi', 'totalHariIni', 'pendapatanHariIni', 'piutangBelumLunas', 'stockAlertCount'
        ))->with('title', 'Daftar Transaksi');
    }

    public function create()
    {
        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('transaksi.create', compact('stockAlertCount'))
            ->with('title', 'Buat Transaksi');
    }

    public function store(Request $request)
    {
        $request->validate([
            'pelanggan' => 'nullable|string|max:100',
            'status' => 'required|in:lunas,piutang',
            'total_bayar' => ['nullable', 'integer', 'min:0', Rule::requiredIf($request->input('status') === 'lunas')],
            'items' => 'required|array|min:1',
            'items.*.barang_id' => 'required|exists:barangs,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.harga_satuan' => 'required|integer|min:0',
            'items.*.diskon' => 'nullable|integer|min:0|max:100',
        ]);

        $qtyPerBarang = collect($request->items)
            ->groupBy('barang_id')
            ->map(fn ($items) => $items->sum('qty'));

        $errors = [];
        foreach ($qtyPerBarang as $barangId => $qtyDiminta) {
            $barang = Barang::find($barangId);
            $tersedia = $this->inventory->stokTersedia($barang);
            if ($tersedia < $qtyDiminta) {
                $errors['items'] = "Stok \"{$barang->nama_barang}\" tidak cukup. Diminta {$qtyDiminta}, tersedia {$tersedia}.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        try {
            DB::transaction(function () use ($request) {
                $totalHarga = 0;
                $items = [];

                foreach ($request->items as $item) {
                    $diskon = $item['diskon'] ?? 0;
                    $subtotal = (int) round($item['qty'] * $item['harga_satuan'] * (1 - $diskon / 100));
                    $totalHarga += $subtotal;
                    $items[] = array_merge($item, ['subtotal' => $subtotal, 'diskon' => $diskon]);
                }

                $transaksi = Transaksi::create([
                    'pelanggan' => $request->pelanggan,
                    'total_harga' => $totalHarga,
                    'total_bayar' => $request->total_bayar ?? 0,
                    'kembalian' => ($request->total_bayar ?? 0) - $totalHarga,
                    'status' => $request->status,
                    'catatan' => $request->catatan,
                    'user_id' => auth()->id(),
                ]);

                foreach ($items as $item) {
                    $barang = Barang::findOrFail($item['barang_id']);

                    $detail = DetailTransaksi::create([
                        'transaksi_id' => $transaksi->id,
                        'barang_id' => $item['barang_id'],
                        'qty' => $item['qty'],
                        'harga_satuan' => $item['harga_satuan'],
                        'diskon' => $item['diskon'],
                        'subtotal' => $item['subtotal'],
                    ]);

                    $rincianBatch = $this->inventory->konsumsiStok($barang, $item['qty'], 'penjualan', $transaksi);

                    foreach ($rincianBatch as $penggunaan) {
                        DetailTransaksiBatch::create([
                            'detail_transaksi_id' => $detail->id,
                            'stock_batch_id' => $penggunaan['batch']->id,
                            'qty' => $penggunaan['qty'],
                            'harga_beli_satuan' => $penggunaan['harga_beli_satuan'],
                        ]);
                    }
                }
            });
        } catch (InsufficientStockException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect()->route('transaksi.index')
            ->with('success', 'Transaksi berhasil disimpan.');
    }

    public function show(Transaksi $transaksi)
    {
        $transaksi->load('detailTransaksi.barang', 'user');
        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('transaksi.show', compact('transaksi', 'stockAlertCount'))
            ->with('title', $transaksi->kode_transaksi);
    }

    public function nota(Transaksi $transaksi)
    {
        $transaksi->load('detailTransaksi.barang', 'user');

        return view('transaksi.nota', compact('transaksi'));
    }

    public function updateStatus(Request $request, Transaksi $transaksi)
    {
        $request->validate(['status' => 'required|in:lunas,piutang,batal']);
        $oldStatus = $transaksi->status;
        $transaksi->update(['status' => $request->status]);

        if ($request->status === 'batal' && $oldStatus !== 'batal') {

            foreach ($transaksi->detailTransaksi as $detail) {
                $this->inventory->pulihkanStokDetailTransaksi($detail, 'pembatalan');
            }
        }

        return back()->with('success', 'Status transaksi berhasil diperbarui.');
    }

    public function destroy(Transaksi $transaksi)
    {
        if ($transaksi->status !== 'batal') {
            return back()->with('error', 'Hanya transaksi berstatus "Batal" yang dapat dihapus.');
        }
        $transaksi->delete();

        return redirect()->route('transaksi.index')
            ->with('success', 'Transaksi berhasil dihapus.');
    }
}
