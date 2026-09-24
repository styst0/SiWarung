@extends('layouts.app')

@section('content')

<div style="margin-bottom:20px;">
    <h1 style="font-size:18px;font-weight:600;color:var(--text-primary);">Import Stok Masuk</h1>
    <p style="font-size:13px;color:var(--text-secondary);margin-top:3px;">Masukkan banyak barang sekaligus dari nota pembelian distributor, lewat file CSV — baik menambah stok barang yang sudah ada, maupun mendaftarkan barang baru sekaligus.</p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card" style="margin-bottom:16px;">
    <div class="card-header">
        <span class="card-title">Cara pakai</span>
    </div>
    <div class="card-body" style="font-size:13px;color:var(--text-secondary);line-height:1.7;">
        <ol style="margin:0;padding-left:18px;">
            <li>Unduh template di bawah, lalu buka pakai Excel.</li>
            <li>Isi satu baris untuk setiap barang yang ada di nota pembelian.</li>
            <li>Kalau <code>kode_barang</code>-nya <strong>sudah terdaftar</strong> di sistem: kolom <code>nama_barang</code>, <code>kategori</code>, <code>satuan</code>, <code>harga_jual</code>, <code>stok_minimum</code> boleh dikosongkan — cuma stoknya yang ditambah.</li>
            <li>Kalau <code>kode_barang</code>-nya <strong>belum ada</strong> di sistem: isi minimal <code>nama_barang</code> supaya barang itu otomatis didaftarkan sekaligus diisi stoknya. Kolom lain (kategori, satuan, harga_jual, stok_minimum) opsional — kalau dikosongkan dipakai nilai wajar (satuan "pcs", stok_minimum 5). Kolom <code>harga_jual</code> boleh diisi manual kalau mau harga tertentu — kalau dikosongkan, sistem otomatis menghitungnya sendiri: harga beli + markup (besarnya tergantung <code>satuan</code>, mis. box/dus lebih besar daripada pcs/bungkus), lalu dibulatkan ke kelipatan Rp 500 terdekat supaya harganya genap dan gampang dipakai untuk pembayaran cash.</li>
            <li>Simpan file dari Excel dengan format <strong>CSV</strong> (bukan .xlsx) — pilih "Save As" → "CSV (Comma delimited)".</li>
            <li>Upload file CSV itu di bawah ini.</li>
        </ol>
        <a href="{{ route('stok-import.template') }}" class="btn btn-outline" style="margin-top:14px;">
            <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M8 2v8m0 0-3-3m3 3 3-3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M2.5 11v1.5A1.5 1.5 0 0 0 4 14h8a1.5 1.5 0 0 0 1.5-1.5V11" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
            Download Template CSV
        </a>
    </div>
</div>

<div class="card" style="max-width:560px;">
    <div class="card-header">
        <span class="card-title">Upload File</span>
    </div>
    <div class="card-body">
        <form action="{{ route('stok-import.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div style="margin-bottom:16px;">
                <label for="file" style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;">File CSV</label>
                <input type="file" id="file" name="file" accept=".csv,.txt" required style="width:100%;padding:9px 10px;border:1px solid #D2D2CC;border-radius:var(--radius-md);font-size:13px;background:var(--bg-white);">
                @error('file')<div style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary">Import</button>
        </form>
    </div>
</div>

@if(session('importErrors'))
    <div class="card" style="margin-top:16px;">
        <div class="card-header">
            <span class="card-title">Baris Bermasalah</span>
        </div>
        <div class="card-body">
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="text-align:left;color:var(--text-secondary);font-size:11px;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #F0F0EC;">
                            <th style="padding:12px 10px;">Baris</th>
                            <th style="padding:12px 10px;">Kode Barang</th>
                            <th style="padding:12px 10px;">Masalah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(session('importErrors') as $err)
                            <tr style="border-bottom:1px solid #F5F5F2;">
                                <td style="padding:12px 10px;color:var(--text-primary);">{{ $err['baris'] }}</td>
                                <td style="padding:12px 10px;">{{ $err['kode_barang'] !== '' ? $err['kode_barang'] : '—' }}</td>
                                <td style="padding:12px 10px;">
                                    @foreach($err['pesan'] as $pesan)
                                        <span class="badge badge-danger" style="margin:2px 4px 2px 0;">{{ $pesan }}</span>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

@endsection
