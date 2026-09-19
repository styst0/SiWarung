<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\StockMovement;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    public function penjualan(Request $request)
    {
        $periode = $request->get('periode', 'harian');
        $dari = $request->get('dari', Carbon::today()->format('Y-m-d'));
        $sampai = $request->get('sampai', Carbon::today()->format('Y-m-d'));

        if ($periode === 'bulanan') {
            $bulan = $request->get('bulan', Carbon::now()->format('Y-m'));
            $dari = Carbon::parse($bulan)->startOfMonth()->format('Y-m-d');
            $sampai = Carbon::parse($bulan)->endOfMonth()->format('Y-m-d');
        }

        $ringkasan = Transaksi::whereBetween(DB::raw('DATE(created_at)'), [$dari, $sampai])
            ->selectRaw('
                COUNT(*) as total_transaksi,
                SUM(CASE WHEN status = "lunas" THEN total_harga ELSE 0 END) as total_pendapatan,
                SUM(CASE WHEN status = "piutang" THEN total_harga ELSE 0 END) as total_piutang,
                SUM(CASE WHEN status = "batal" THEN 1 ELSE 0 END) as total_batal
            ')
            ->first();

        $grafikHarian = Transaksi::whereBetween(DB::raw('DATE(created_at)'), [$dari, $sampai])
            ->where('status', 'lunas')
            ->selectRaw('DATE(created_at) as tanggal, SUM(total_harga) as total, COUNT(*) as jumlah')
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get();

        $barangTerlaris = DB::table('detail_transaksis')
            ->join('transaksis', 'detail_transaksis.transaksi_id', '=', 'transaksis.id')
            ->join('barangs', 'detail_transaksis.barang_id', '=', 'barangs.id')
            ->whereBetween(DB::raw('DATE(transaksis.created_at)'), [$dari, $sampai])
            ->where('transaksis.status', '!=', 'batal')
            ->selectRaw('
                barangs.nama_barang,
                barangs.satuan,
                SUM(detail_transaksis.qty) as total_qty,
                SUM(detail_transaksis.subtotal) as total_nilai
            ')
            ->groupBy('barangs.id', 'barangs.nama_barang', 'barangs.satuan')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        $perKategori = DB::table('detail_transaksis')
            ->join('transaksis', 'detail_transaksis.transaksi_id', '=', 'transaksis.id')
            ->join('barangs', 'detail_transaksis.barang_id', '=', 'barangs.id')
            ->whereBetween(DB::raw('DATE(transaksis.created_at)'), [$dari, $sampai])
            ->where('transaksis.status', '!=', 'batal')
            ->selectRaw('barangs.kategori, SUM(detail_transaksis.subtotal) as total')
            ->groupBy('barangs.kategori')
            ->orderByDesc('total')
            ->get();

        $transaksi = Transaksi::whereBetween(DB::raw('DATE(created_at)'), [$dari, $sampai])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('laporan.penjualan', compact(
            'periode', 'dari', 'sampai',
            'ringkasan', 'grafikHarian',
            'barangTerlaris', 'perKategori',
            'transaksi', 'stockAlertCount',
        ))->with('title', 'Laporan Penjualan');
    }

    public function labaRugi(Request $request)
    {
        $bulan = $request->get('bulan', Carbon::now()->format('Y-m'));
        $dari = Carbon::parse($bulan)->startOfMonth()->format('Y-m-d');
        $sampai = Carbon::parse($bulan)->endOfMonth()->format('Y-m-d');

        $pendapatan = Transaksi::whereBetween(DB::raw('DATE(created_at)'), [$dari, $sampai])
            ->where('status', 'lunas')
            ->sum('total_harga');

        $hpp = DB::table('detail_transaksis')
            ->join('transaksis', 'detail_transaksis.transaksi_id', '=', 'transaksis.id')
            ->join('barangs', 'detail_transaksis.barang_id', '=', 'barangs.id')
            ->whereBetween(DB::raw('DATE(transaksis.created_at)'), [$dari, $sampai])
            ->where('transaksis.status', 'lunas')
            ->selectRaw('SUM('.$this->hppFifoExpr().') as total_hpp')
            ->value('total_hpp') ?? 0;

        $labaKotor = $pendapatan - $hpp;
        $marginPersen = $pendapatan > 0 ? round(($labaKotor / $pendapatan) * 100, 1) : 0;

        $labaPerKategori = DB::table('detail_transaksis')
            ->join('transaksis', 'detail_transaksis.transaksi_id', '=', 'transaksis.id')
            ->join('barangs', 'detail_transaksis.barang_id', '=', 'barangs.id')
            ->whereBetween(DB::raw('DATE(transaksis.created_at)'), [$dari, $sampai])
            ->where('transaksis.status', 'lunas')
            ->selectRaw('
                barangs.kategori,
                SUM(detail_transaksis.subtotal) as pendapatan,
                SUM('.$this->hppFifoExpr().') as hpp,
                SUM(detail_transaksis.subtotal - ('.$this->hppFifoExpr().')) as laba
            ')
            ->groupBy('barangs.kategori')
            ->orderByDesc('laba')
            ->get();

        $labaPerBarang = DB::table('detail_transaksis')
            ->join('transaksis', 'detail_transaksis.transaksi_id', '=', 'transaksis.id')
            ->join('barangs', 'detail_transaksis.barang_id', '=', 'barangs.id')
            ->whereBetween(DB::raw('DATE(transaksis.created_at)'), [$dari, $sampai])
            ->where('transaksis.status', 'lunas')
            ->selectRaw('
                barangs.nama_barang,
                barangs.kategori,
                SUM(detail_transaksis.qty) as qty_terjual,
                SUM(detail_transaksis.subtotal) as pendapatan,
                SUM('.$this->hppFifoExpr().') as hpp,
                SUM(detail_transaksis.subtotal - ('.$this->hppFifoExpr().')) as laba
            ')
            ->groupBy('barangs.id', 'barangs.nama_barang', 'barangs.kategori')
            ->orderByDesc('laba')
            ->limit(10)
            ->get();

        $trendHarian = DB::table('detail_transaksis')
            ->join('transaksis', 'detail_transaksis.transaksi_id', '=', 'transaksis.id')
            ->join('barangs', 'detail_transaksis.barang_id', '=', 'barangs.id')
            ->whereBetween(DB::raw('DATE(transaksis.created_at)'), [$dari, $sampai])
            ->where('transaksis.status', 'lunas')
            ->selectRaw('
                DATE(transaksis.created_at) as tanggal,
                SUM(detail_transaksis.subtotal) as pendapatan,
                SUM('.$this->hppFifoExpr().') as hpp,
                SUM(detail_transaksis.subtotal - ('.$this->hppFifoExpr().')) as laba
            ')
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get();

        $bulanLaluDari = Carbon::parse($bulan)->subMonth()->startOfMonth()->format('Y-m-d');
        $bulanLaluSampai = Carbon::parse($bulan)->subMonth()->endOfMonth()->format('Y-m-d');

        $pendapatanBulanLalu = Transaksi::whereBetween(DB::raw('DATE(created_at)'), [$bulanLaluDari, $bulanLaluSampai])
            ->where('status', 'lunas')->sum('total_harga');

        $hppBulanLalu = DB::table('detail_transaksis')
            ->join('transaksis', 'detail_transaksis.transaksi_id', '=', 'transaksis.id')
            ->join('barangs', 'detail_transaksis.barang_id', '=', 'barangs.id')
            ->whereBetween(DB::raw('DATE(transaksis.created_at)'), [$bulanLaluDari, $bulanLaluSampai])
            ->where('transaksis.status', 'lunas')
            ->selectRaw('SUM('.$this->hppFifoExpr().') as total_hpp')
            ->value('total_hpp') ?? 0;

        $labaBulanLalu = $pendapatanBulanLalu - $hppBulanLalu;
        $persenLabaChange = $labaBulanLalu > 0
            ? round((($labaKotor - $labaBulanLalu) / $labaBulanLalu) * 100, 1)
            : 0;

        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('laporan.laba-rugi', compact(
            'bulan', 'dari', 'sampai',
            'pendapatan', 'hpp', 'labaKotor', 'marginPersen',
            'labaPerKategori', 'labaPerBarang', 'trendHarian',
            'pendapatanBulanLalu', 'labaBulanLalu', 'persenLabaChange',
            'stockAlertCount',
        ))->with('title', 'Laporan Laba Rugi');
    }

    protected function hppFifoExpr(): string
    {
        return '
            COALESCE(
                (SELECT SUM(dtb.qty * dtb.harga_beli_satuan)
                 FROM detail_transaksi_batches dtb
                 WHERE dtb.detail_transaksi_id = detail_transaksis.id),
                detail_transaksis.qty * barangs.harga_beli
            )
        ';
    }

    public function penyusutan(Request $request)
    {
        $bulan = $request->get('bulan', Carbon::now()->format('Y-m'));
        $dari = Carbon::parse($bulan)->startOfMonth()->format('Y-m-d');
        $sampai = Carbon::parse($bulan)->endOfMonth()->format('Y-m-d');

        $query = StockMovement::query()
            ->where('reason', 'penyusutan')
            ->whereBetween(DB::raw('DATE(stock_movements.created_at)'), [$dari, $sampai]);

        $ringkasan = (clone $query)
            ->selectRaw('COUNT(*) as jumlah_kejadian, COALESCE(SUM(qty), 0) as total_qty, COALESCE(SUM(qty * harga_satuan), 0) as total_nilai')
            ->first();

        $perKategori = (clone $query)
            ->join('barangs', 'stock_movements.barang_id', '=', 'barangs.id')
            ->selectRaw('barangs.kategori, SUM(stock_movements.qty) as total_qty, SUM(stock_movements.qty * stock_movements.harga_satuan) as total_nilai')
            ->groupBy('barangs.kategori')
            ->orderByDesc('total_nilai')
            ->get();

        $perBarang = (clone $query)
            ->join('barangs', 'stock_movements.barang_id', '=', 'barangs.id')
            ->selectRaw('barangs.nama_barang, barangs.kategori, SUM(stock_movements.qty) as total_qty, SUM(stock_movements.qty * stock_movements.harga_satuan) as total_nilai')
            ->groupBy('barangs.id', 'barangs.nama_barang', 'barangs.kategori')
            ->orderByDesc('total_nilai')
            ->limit(10)
            ->get();

        $riwayat = (clone $query)
            ->with('barang', 'stockBatch')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stockAlertCount = Barang::whereColumn('stok', '<=', 'stok_minimum')->count();

        return view('laporan.penyusutan', compact(
            'bulan', 'dari', 'sampai',
            'ringkasan', 'perKategori', 'perBarang', 'riwayat',
            'stockAlertCount',
        ))->with('title', 'Laporan Penyusutan');
    }
}
