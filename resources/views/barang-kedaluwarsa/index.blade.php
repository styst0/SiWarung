@extends('layouts.app')

@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <div>
        <h1 style="font-size:18px;font-weight:600;color:var(--text-primary);">Barang Kedaluwarsa</h1>
        <p style="font-size:13px;color:var(--text-secondary);margin-top:3px;">
            {{ $batchSudahKedaluwarsa->total() }} batch sudah kedaluwarsa · {{ $batchAkanKedaluwarsa->total() }} batch akan kedaluwarsa dalam 7 hari
        </p>
    </div>
    <a href="{{ route('laporan.penyusutan') }}" class="btn btn-outline">Laporan penyusutan<x-link-arrow /></a>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-header">
        <span class="card-title">Sudah Kedaluwarsa</span>
        <span style="font-size:12px;color:var(--text-muted);">{{ $batchSudahKedaluwarsa->total() }} batch</span>
    </div>
    <div style="padding:12px 18px;">
        @if($batchSudahKedaluwarsa->isEmpty())
            <div style="padding:20px 0;text-align:center;color:var(--text-muted);font-size:13px;">Tidak ada batch yang sudah kedaluwarsa 🎉</div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;">
                @foreach($batchSudahKedaluwarsa as $batch)
                <div style="background:var(--danger-bg);border:1px solid #F7C1C1;border-radius:var(--radius-md);padding:12px 14px;">
                    <div style="font-size:12px;font-weight:600;color:var(--text-primary);margin-bottom:4px;line-height:1.3;">{{ $batch->barang->nama_barang }}</div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-bottom:8px;">
                        {{ $batch->kode_batch }} · Sisa {{ $batch->qty_tersisa }} {{ $batch->barang->satuan }}
                    </div>
                    <span class="badge badge-danger">Kedaluwarsa {{ $batch->tanggal_kedaluwarsa->format('d M Y') }}</span>
                    <a href="{{ route('barang.show', $batch->barang) }}" style="display:block;margin-top:8px;font-size:11px;color:var(--danger-text);font-weight:600;">Catat penyusutan<x-link-arrow /></a>
                </div>
                @endforeach
            </div>
            <div style="margin-top:16px;">
                {{ $batchSudahKedaluwarsa->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Akan Kedaluwarsa (7 Hari ke Depan)</span>
        <span style="font-size:12px;color:var(--text-muted);">{{ $batchAkanKedaluwarsa->total() }} batch</span>
    </div>
    <div style="padding:12px 18px;">
        @if($batchAkanKedaluwarsa->isEmpty())
            <div style="padding:20px 0;text-align:center;color:var(--text-muted);font-size:13px;">Tidak ada batch yang akan kedaluwarsa dalam 7 hari ke depan</div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;">
                @foreach($batchAkanKedaluwarsa as $batch)
                <div style="background:var(--warning-bg);border:1px solid #FAC775;border-radius:var(--radius-md);padding:12px 14px;">
                    <div style="font-size:12px;font-weight:600;color:var(--text-primary);margin-bottom:4px;line-height:1.3;">{{ $batch->barang->nama_barang }}</div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-bottom:8px;">
                        {{ $batch->kode_batch }} · Sisa {{ $batch->qty_tersisa }} {{ $batch->barang->satuan }}
                    </div>
                    <span class="badge badge-warning">Kedaluwarsa {{ $batch->tanggal_kedaluwarsa->format('d M Y') }}</span>
                    <a href="{{ route('barang.show', $batch->barang) }}" style="display:block;margin-top:8px;font-size:11px;color:var(--warning-text);font-weight:600;">Lihat barang<x-link-arrow /></a>
                </div>
                @endforeach
            </div>
            <div style="margin-top:16px;">
                {{ $batchAkanKedaluwarsa->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</div>

@endsection
