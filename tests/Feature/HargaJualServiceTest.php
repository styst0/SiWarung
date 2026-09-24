<?php

use App\Services\HargaJualService;

test('markup satuan bungkus dibulatkan ke kelipatan 500 terdekat di atas rentang minimum', function () {
    $service = new HargaJualService;

    // Kopi sachet ABC Mocca 27gr: harga beli 1551, satuan bungkus (markup 300-800).
    // 1551+300=1851 -> genap 500 berikutnya = 2000 (masih <= 1551+800=2351).
    expect($service->hitungOtomatis(1551, 'bungkus'))->toBe(2000);
});

test('markup satuan dus/dos/box dianggap sama (alias)', function () {
    $service = new HargaJualService;

    $dariDus = $service->hitungOtomatis(61262, 'dus');
    $dariDos = $service->hitungOtomatis(61262, 'dos');
    $dariBox = $service->hitungOtomatis(61262, 'box');

    expect($dariDus)->toBe($dariDos)->toBe($dariBox);
});

test('markup satuan karton/ktn dianggap sama dan berhenti di batas bawah kalau sudah genap', function () {
    $service = new HargaJualService;

    // 100000 + markup minimum karton (2000) = 102000, sudah kelipatan 500 -> dipakai langsung.
    expect($service->hitungOtomatis(100000, 'karton'))->toBe(102000);
    expect($service->hitungOtomatis(100000, 'ktn'))->toBe(102000);
});

test('pencocokan satuan tidak peduli besar-kecil huruf dan spasi', function () {
    $service = new HargaJualService;

    expect($service->hitungOtomatis(1551, ' BUNGKUS '))->toBe(2000);
    expect($service->hitungOtomatis(1551, 'Bungkus'))->toBe(2000);
});

test('satuan yang tidak dikenal pakai rentang markup default', function () {
    $service = new HargaJualService;

    // 'krat' tidak ada di config -> pakai default [500, 1500].
    // 10000+500=10500, sudah kelipatan 500 -> dipakai langsung.
    expect($service->hitungOtomatis(10000, 'krat'))->toBe(10500);
});

test('harga jual otomatis tidak pernah lebih kecil dari harga beli walau rentang markup dikonfigurasi negatif', function () {
    config(['markup_harga.rentang.aneh' => [-1000, -500]]);
    $service = new HargaJualService;

    $hasil = $service->hitungOtomatis(1000, 'aneh');

    expect($hasil)->toBeGreaterThanOrEqual(1000);
});

test('rentang markup yang lebih sempit dari kelipatan pembulatan tetap menghasilkan harga yang valid', function () {
    config(['markup_harga.rentang.sempit' => [100, 150]]);
    $service = new HargaJualService;

    // 1000+100=1100 s.d. 1000+150=1150 -> tidak ada kelipatan 500 di antaranya,
    // jadi dipakai kelipatan 500 tertinggi yang masih <= batas atas (1000).
    expect($service->hitungOtomatis(1000, 'sempit'))->toBe(1000);
});
