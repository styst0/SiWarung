<?php

namespace App\Providers;

use App\Models\StockBatch;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {

        Paginator::defaultView('vendor.pagination.siwarung');

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
