@extends('layouts.app')

@section('content')

<div style="margin-bottom:20px;">
    <a href="{{ route('barang.show', $barang) }}" style="font-size:12px;color:var(--text-muted);text-decoration:none;margin-bottom:10px;display:inline-block;"><x-link-arrow direction="left" />Kembali ke {{ $barang->nama_barang }}</a>
    <div style="display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h1 style="font-size:18px;font-weight:600;color:var(--text-primary);">Tambah Stok</h1>
            <p style="font-size:13px;color:var(--text-secondary);margin-top:3px;">Setiap stok masuk dicatat sebagai satu batch penerimaan baru dan akan dikonsumsi lebih dulu saat terjual (metode FIFO).</p>
        </div>
    </div>
</div>

<div class="card" style="max-width:560px;">
    <div class="card-body">
        <form action="{{ route('barang.stok-masuk.store', $barang) }}" method="POST">
            @csrf

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;">Produk</label>
                <div style="padding:12px 14px;border:1px solid #E8E8E4;border-radius:var(--radius-md);background:#F8F9F6;">{{ $barang->nama_barang }} ({{ $barang->kode_barang }})</div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div>
                    <label for="qty" style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;">Jumlah Stok Masuk</label>
                    <input id="qty" name="qty" type="number" min="1" value="{{ old('qty', 1) }}" required style="width:100%;padding:11px 12px;border:1px solid #D2D2CC;border-radius:var(--radius-md);font-size:13px;" />
                    @error('qty')<div style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="harga_beli_satuan" style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;">Harga Beli / Satuan (Rp)</label>
                    <input id="harga_beli_satuan" name="harga_beli_satuan" type="number" min="0" value="{{ old('harga_beli_satuan', $barang->harga_beli) }}" style="width:100%;padding:11px 12px;border:1px solid #D2D2CC;border-radius:var(--radius-md);font-size:13px;" />
                    @error('harga_beli_satuan')<div style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div>
                    <label for="tanggal_terima" style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;">Tanggal Terima</label>
                    <input id="tanggal_terima" name="tanggal_terima" type="date" value="{{ old('tanggal_terima', now()->toDateString()) }}" style="width:100%;padding:11px 12px;border:1px solid #D2D2CC;border-radius:var(--radius-md);font-size:13px;" />
                    @error('tanggal_terima')<div style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="tanggal_kedaluwarsa" style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;">Tanggal Kedaluwarsa (opsional)</label>
                    <input id="tanggal_kedaluwarsa" name="tanggal_kedaluwarsa" type="date" value="{{ old('tanggal_kedaluwarsa') }}" style="width:100%;padding:11px 12px;border:1px solid #D2D2CC;border-radius:var(--radius-md);font-size:13px;" />
                    @error('tanggal_kedaluwarsa')<div style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label for="supplier" style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;">Pemasok (opsional)</label>
                <input id="supplier" name="supplier" type="text" value="{{ old('supplier') }}" placeholder="Nama toko/distributor" style="width:100%;padding:11px 12px;border:1px solid #D2D2CC;border-radius:var(--radius-md);font-size:13px;" />
                @error('supplier')<div style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>

            <div style="margin-bottom:18px;">
                <label for="notes" style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:6px;">Catatan (opsional)</label>
                <textarea id="notes" name="notes" rows="3" style="width:100%;padding:11px 12px;border:1px solid #D2D2CC;border-radius:var(--radius-md);font-size:13px;">{{ old('notes') }}</textarea>
                @error('notes')<div style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary">Simpan Stok Masuk</button>
        </form>
    </div>
</div>

@endsection
