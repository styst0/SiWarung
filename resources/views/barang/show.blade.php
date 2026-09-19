@extends('layouts.app')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('barang.index') }}" style="font-size:12px;color:var(--text-muted);text-decoration:none;margin-bottom:10px;display:inline-block;"><x-link-arrow direction="left" />Kembali ke Data Barang</a>
    <div style="display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h1 style="font-size:18px;font-weight:600;color:var(--text-primary);">{{ $barang->nama_barang }}</h1>
            <p style="font-size:13px;color:var(--text-secondary);margin-top:3px;">{{ $barang->kode_barang }}</p>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('barang.edit', $barang) }}" class="btn btn-primary">Edit Barang</a>
            <a href="{{ route('barang.stok-masuk', $barang) }}" class="btn btn-outline">Tambah Stok</a>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:800px;">

    {{-- INFO UTAMA --}}
    <div class="card">
        <div class="card-header"><span class="card-title">Informasi Barang</span></div>
        <div class="card-body">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr style="border-bottom:1px solid #F5F5F2;">
                    <td style="padding:9px 0;color:var(--text-muted);width:40%;">Kode Barang</td>
                    <td style="padding:9px 0;font-weight:500;color:var(--text-primary);">{{ $barang->kode_barang }}</td>
                </tr>
                <tr style="border-bottom:1px solid #F5F5F2;">
                    <td style="padding:9px 0;color:var(--text-muted);">Nama Barang</td>
                    <td style="padding:9px 0;font-weight:500;color:var(--text-primary);">{{ $barang->nama_barang }}</td>
                </tr>
                <tr style="border-bottom:1px solid #F5F5F2;">
                    <td style="padding:9px 0;color:var(--text-muted);">Kategori</td>
                    <td style="padding:9px 0;">{{ $barang->kategori ?? '—' }}</td>
                </tr>
                <tr style="border-bottom:1px solid #F5F5F2;">
                    <td style="padding:9px 0;color:var(--text-muted);">Satuan</td>
                    <td style="padding:9px 0;">{{ $barang->satuan }}</td>
                </tr>
                <tr>
                    <td style="padding:9px 0;color:var(--text-muted);">Deskripsi</td>
                    <td style="padding:9px 0;color:var(--text-secondary);">{{ $barang->deskripsi ?? '—' }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- HARGA & STOK --}}
    <div style="display:flex;flex-direction:column;gap:14px;">
        <div class="card">
            <div class="card-header"><span class="card-title">Harga</span></div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="background:var(--bg-page);border-radius:var(--radius-md);padding:12px;">
                        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Harga Beli</div>
                        <div style="font-size:16px;font-weight:600;color:var(--text-primary);">Rp {{ number_format($barang->harga_beli, 0, ',', '.') }}</div>
                    </div>
                    <div style="background:var(--bg-page);border-radius:var(--radius-md);padding:12px;">
                        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Harga Jual</div>
                        <div style="font-size:16px;font-weight:600;color:var(--brand);">Rp {{ number_format($barang->harga_jual, 0, ',', '.') }}</div>
                    </div>
                </div>
                @php $margin = $barang->harga_jual - $barang->harga_beli; $persen = $barang->harga_beli > 0 ? round(($margin / $barang->harga_beli) * 100) : 0; @endphp
                <div style="margin-top:10px;padding:8px 12px;background:var(--success-bg);border-radius:var(--radius-md);font-size:12px;color:var(--success-text);">
                    Margin: Rp {{ number_format($margin, 0, ',', '.') }} ({{ $persen }}%)
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Stok</span></div>
            <div class="card-body">
                <div style="display:flex;align-items:center;gap:16px;">
                    <div style="text-align:center;">
                        <div style="font-size:32px;font-weight:600;color:{{ $barang->stok <= $barang->stok_minimum ? 'var(--danger-text)' : 'var(--text-primary)' }};">{{ $barang->stok }}</div>
                        <div style="font-size:12px;color:var(--text-muted);">{{ $barang->satuan }}</div>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px;">Stok minimum: <strong>{{ $barang->stok_minimum }}</strong></div>
                        @if($barang->stok <= 0)
                            <span class="badge badge-danger">Habis</span>
                        @elseif($barang->stok <= ($barang->stok_minimum / 2))
                            <span class="badge badge-danger">Kritis — segera restock</span>
                        @elseif($barang->stok <= $barang->stok_minimum)
                            <span class="badge badge-warning">Stok Rendah</span>
                        @else
                            <span class="badge badge-success">Stok Aman</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- BATCH PENERIMAAN (FIFO) & KEDALUWARSA --}}
<div class="card" style="max-width:800px;margin-top:14px;">
    <div class="card-header">
        <span class="card-title">Batch Penerimaan Barang (FIFO)</span>
        @if($batchKedaluwarsa > 0)
            <span class="badge badge-danger">{{ $batchKedaluwarsa }} batch kedaluwarsa</span>
        @elseif($batchAkanKedaluwarsa > 0)
            <span class="badge badge-warning">{{ $batchAkanKedaluwarsa }} batch akan kedaluwarsa</span>
        @endif
    </div>
    <div class="card-body">
        <p style="font-size:12px;color:var(--text-secondary);margin-bottom:14px;">
            Saat barang ini terjual, stok diambil lebih dulu dari batch paling atas (diterima paling awal / First In First Out).
        </p>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="text-align:left;color:var(--text-secondary);font-size:11px;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #F0F0EC;">
                        <th style="padding:10px 8px;">Batch</th>
                        <th style="padding:10px 8px;">Diterima</th>
                        <th style="padding:10px 8px;">Kedaluwarsa</th>
                        <th style="padding:10px 8px;">Sisa</th>
                        <th style="padding:10px 8px;">Harga Beli</th>
                        <th style="padding:10px 8px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                        <tr style="border-bottom:1px solid #F5F5F2;vertical-align:top;">
                            <td style="padding:10px 8px;font-weight:500;">{{ $batch->kode_batch }}</td>
                            <td style="padding:10px 8px;">{{ $batch->tanggal_terima->format('d M Y') }}</td>
                            <td style="padding:10px 8px;">
                                @if(!$batch->tanggal_kedaluwarsa)
                                    <span style="color:var(--text-muted);">—</span>
                                @elseif($batch->status_kedaluwarsa === 'kedaluwarsa')
                                    <span class="badge badge-danger">{{ $batch->tanggal_kedaluwarsa->format('d M Y') }}</span>
                                @elseif($batch->status_kedaluwarsa === 'segera')
                                    <span class="badge badge-warning">{{ $batch->tanggal_kedaluwarsa->format('d M Y') }}</span>
                                @else
                                    {{ $batch->tanggal_kedaluwarsa->format('d M Y') }}
                                @endif
                            </td>
                            <td style="padding:10px 8px;">{{ $batch->qty_tersisa }} / {{ $batch->qty_masuk }} {{ $barang->satuan }}</td>
                            <td style="padding:10px 8px;">Rp {{ number_format($batch->harga_beli_satuan, 0, ',', '.') }}</td>
                            <td style="padding:10px 8px;">
                                <details>
                                    <summary style="cursor:pointer;color:var(--danger-text);font-size:12px;">Catat penyusutan</summary>
                                    <form action="{{ route('barang.batch.penyusutan', [$barang, $batch]) }}" method="POST" style="margin-top:8px;display:flex;flex-direction:column;gap:6px;min-width:220px;">
                                        @csrf
                                        <input type="number" name="qty" min="1" max="{{ $batch->qty_tersisa }}" value="{{ $batch->qty_tersisa }}" required
                                            style="padding:6px 8px;border:1px solid #D2D2CC;border-radius:var(--radius-sm);font-size:12px;">
                                        <select name="alasan" required style="padding:6px 8px;border:1px solid #D2D2CC;border-radius:var(--radius-sm);font-size:12px;">
                                            <option value="kedaluwarsa">Kedaluwarsa</option>
                                            <option value="rusak">Rusak</option>
                                            <option value="hilang">Hilang</option>
                                            <option value="lainnya">Lainnya</option>
                                        </select>
                                        <input type="text" name="catatan" placeholder="Catatan (opsional)" style="padding:6px 8px;border:1px solid #D2D2CC;border-radius:var(--radius-sm);font-size:12px;">
                                        <button type="submit" class="btn btn-outline" style="font-size:12px;padding:5px 10px;">Simpan</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:16px 8px;text-align:center;color:var(--text-muted);">Belum ada batch dengan sisa stok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection