@props(['direction' => 'right'])

{{--
    Ikon panah kecil untuk penanda link ("Lihat detail", "Kelola barang",
    "Kembali ke ...", dst). Dipakai di seluruh app sebagai pengganti karakter
    "→" / "←" supaya ukuran & gayanya konsisten di semua halaman (tidak
    bergantung pada rendering font browser untuk karakter unicode). Warnanya
    otomatis mengikuti warna teks link (currentColor).

    - direction="right" (default): untuk link maju, ditaruh SETELAH teks
      (mis. "Lihat semua<x-link-arrow />").
    - direction="left": untuk link kembali/back, ditaruh SEBELUM teks
      (mis. "<x-link-arrow direction="left" />Kembali ke ...").
--}}
@if($direction === 'left')
<svg width="10" height="10" viewBox="0 0 16 16" fill="none" style="display:inline-block;vertical-align:1px;margin-right:3px;flex-shrink:0;">
    <path d="M10 3.5 5.5 8 10 12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
@else
<svg width="10" height="10" viewBox="0 0 16 16" fill="none" style="display:inline-block;vertical-align:1px;margin-left:3px;flex-shrink:0;">
    <path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
@endif
