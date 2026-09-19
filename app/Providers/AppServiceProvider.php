<?php

namespace App\Providers;

use App\Models\StockBatch;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // View pagination bawaan Laravel ("tailwind") memakai class Tailwind
        // untuk ukuran ikon panahnya, yang tidak pernah ter-compile di app
        // ini (lihat resources/views/vendor/pagination/siwarung.blade.php),
        // jadi ikonnya tampil raksasa & tidak ter-style. Pakai view kustom
        // sendiri sebagai default di semua halaman yang memanggil ->links().
        Paginator::defaultView('vendor.pagination.siwarung');

        // Badge "barang akan/sudah kedaluwarsa" di sidebar (layouts.app)
        // dibagikan lewat view composer supaya tidak perlu dihitung ulang
        // secara manual di setiap controller, mengikuti pola stockAlertCount
        // yang sudah ada tapi tanpa menduplikasi query di setiap method.
        View::composer('layouts.app', function ($view) {
            if (! $view->offsetExists('expiryAlertCount')) {
                $view->with('expiryAlertCount',
                    StockBatch::aktif()->akanKedaluwarsa()->count()
                    + StockBatch::aktif()->sudahKedaluwarsa()->count()
                );
            }
        });
    }
}
