<?php

namespace App\Providers;

use App\Models\StockBatch;
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
