{{--
    View pagination kustom untuk SiWarung.

    Kenapa ini ada: view bawaan Laravel ("tailwind") memakai class Tailwind
    (mis. `class="w-5 h-5"`) untuk mengecilkan ikon panah SVG-nya. Tailwind
    di app ini dikonfigurasi tapi tidak pernah benar-benar di-compile/di-load
    oleh layout utama, jadi SVG panahnya tampil tanpa ukuran sama sekali
    (default browser, sangat besar). View ini pakai gaya inline yang sama
    seperti komponen lain di app (lihat class .pg-* di layouts/app.blade.php)
    supaya tombolnya kecil & konsisten tanpa bergantung pada Tailwind.
--}}
@if ($paginator->hasPages())
<nav class="pg-nav" role="navigation" aria-label="{{ __('Pagination Navigation') }}">

    {{-- Previous --}}
    @if ($paginator->onFirstPage())
        <span class="pg-item pg-disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3 5 8l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pg-item" aria-label="{{ __('pagination.previous') }}">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M10 3 5 8l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
    @endif

    {{-- Nomor halaman --}}
    @foreach ($elements as $element)
        @if (is_string($element))
            <span class="pg-dots">{{ $element }}</span>
        @endif

        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="pg-item pg-current" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="pg-item" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Next --}}
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pg-item" aria-label="{{ __('pagination.next') }}">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
    @else
        <span class="pg-item pg-disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
    @endif
</nav>
@endif
