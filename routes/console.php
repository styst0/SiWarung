<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Peringatan otomatis barang kedaluwarsa, dijalankan setiap pagi.
Schedule::command('barang:cek-kedaluwarsa')->dailyAt('07:00');
