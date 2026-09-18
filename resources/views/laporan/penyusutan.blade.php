@extends('layouts.app')

@section('content')

{{-- HEADER --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;">
    <div>
        <h1 style="font-size:18px;font-weight:600;color:var(--text-primary);">Laporan Penyusutan</h1>
        <p style="font-size:13px;color:var(--text-secondary);margin-top:3px;">
            {{ \Carbon\Carbon::parse($dari)->format('d M') }} — {{ \Carbon\Carbon::parse($sampai)->format('d M Y') }}
            · Barang kedaluwarsa, rusak, atau hilang yang dikeluarkan dari stok
        </p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <form method="GET" action="{{ route('laporan.penyusutan') }}" style="display:flex;gap:8px;align-items:center;">
            <input type="month" name="bulan" value="{{ $bulan }}"
                style="padding:7px 12px;border:var(--border);border-radius:var(--radius-md);font-size:13px;font-family:var(--font);color:var(--text-primary);outline:none;">
            <button type="submit" class="btn btn-primary" style="padding:7px 16px;">Tampilkan</button>
        </form>
        <button onclick="window.print()" class="btn btn-outline">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="2" y="5" width="12" height="8" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M5 5V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M5 11h6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
            Cetak
        </button>
    </div>
</div>

{{-- RINGKASAN --}}
<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px;">
    <div class="card">
        <div class="card-body">
            <div style="font-size:11px;color:var(--text-secondary);margin-bottom:4px;">Kejadian Penyusutan</div>
            <div style="font-size:22px;font-weight:600;color:var(--text-primary);">{{ number_format($ringkasan->jumlah_kejadian) }}</div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">kali dicatat bulan ini</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div style="font-size:11px;color:var(--text-secondary);margin-bottom:4px;">Total Unit Susut</div>
            <div style="font-size:22px;font-weight:600;color:var(--text-primary);">{{ number_format($ringkasan->total_qty) }}</div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">unit dikeluarkan dari stok</div>
        </div>
    </div>
    <div class="card" style="border-color:#F7C1C1;">
        <div class="card-body">
            <div style="font-size:11px;color:var(--text-secondary);margin-bottom:4px;">Estimasi Kerugian</div>
            <div style="font-size:18px;font-weight:600;color:var(--danger-text);">Rp {{ number_format($ringkasan->total_nilai, 0, ',', '.') }}</div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">dihitung dari harga beli batch</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
    {{-- PER KATEGORI --}}
    <div class="card">
        <div class="card-header"><span class="card-title">Penyusutan per Kategori</span></div>
        <div class="card-body">
            @forelse($perKategori as $kat)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F5F5F2;">
                    <span style="font-size:13px;color:var(--text-primary);">{{ $kat->kategori ?? 'Lainnya' }}</span>
                    <span style="font-size:13px;color:var(--text-secondary);">{{ $kat->total_qty }} unit</span>
                    <span style="font-size:13px;font-weight:600;color:var(--danger-text);">Rp {{ number_format($kat->total_nilai, 0, ',', '.') }}</span>
                </div>
            @empty
                <div style="padding:20px 0;text-align:center;color:var(--text-muted);font-size:13px;">Belum ada penyusutan bulan ini</div>
            @endforelse
        </div>
    </div>

    {{-- PER BARANG --}}
    <div class="card">
        <div class="card-header"><span class="card-title">Barang Paling Banyak Susut</span></div>
        <div class="card-body">
            @forelse($perBarang as $b)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F5F5F2;">
                    <div>
                        <div style="font-size:13px;color:var(--text-primary);">{{ $b->nama_barang }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">{{ $b->kategori ?? '—' }}</div>
                    </div>
                    <span style="font-size:13px;color:var(--text-secondary);">{{ $b->total_qty }} unit</span>
                    <span style="font-size:13px;font-weight:600;color:var(--danger-text);">Rp {{ number_format($b->total_nilai, 0, ',', '.') }}</span>
                </div>
            @empty
                <div style="padding:20px 0;text-align:center;color:var(--text-muted);font-size:13px;">Belum ada penyusutan bulan ini</div>
            @endforelse
        </div>
    </div>
</div>

{{-- RIWAYAT --}}
<div class="card">
    <div class="card-header"><span class="card-title">Riwayat Penyusutan</span></div>
    <div class="card-body">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="text-align:left;color:var(--text-secondary);font-size:11px;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #F0F0EC;">
                        <th style="padding:12px 10px;">Tanggal</th>
                        <th style="padding:12px 10px;">Barang</th>
                        <th style="padding:12px 10px;">Batch</th>
                        <th style="padding:12px 10px;">Qty</th>
                        <th style="padding:12px 10px;">Nilai</th>
                        <th style="padding:12px 10px;">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($riwayat as $item)
                        <tr style="border-bottom:1px solid #F5F5F2;">
                            <td style="padding:12px 10px;color:var(--text-primary);">{{ $item->created_at->format('d M Y H:i') }}</td>
                            <td style="padding:12px 10px;">{{ $item->barang->nama_barang ?? '—' }}</td>
                            <td style="padding:12px 10px;color:var(--text-muted);">{{ $item->stockBatch->kode_batch ?? '—' }}</td>
                            <td style="padding:12px 10px;">{{ $item->qty }}</td>
                            <td style="padding:12px 10px;color:var(--danger-text);font-weight:500;">Rp {{ number_format($item->qty * ($item->harga_satuan ?? 0), 0, ',', '.') }}</td>
                            <td style="padding:12px 10px;color:var(--text-secondary);">{{ $item->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:20px 10px;text-align:center;color:var(--text-muted);">Belum ada penyusutan tercatat pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:18px;">{{ $riwayat->links() }}</div>
    </div>
</div>

@endsection
