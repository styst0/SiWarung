<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pembulatan harga jual otomatis
    |--------------------------------------------------------------------------
    |
    | Setiap harga jual yang dihitung otomatis (bukan diisi manual) akan
    | dibulatkan ke kelipatan angka ini, supaya "genap" dan gampang dipakai
    | untuk pembayaran cash (tidak ada pecahan uang yang aneh).
    |
    */
    'pembulatan' => 500,

    /*
    |--------------------------------------------------------------------------
    | Alias satuan
    |--------------------------------------------------------------------------
    |
    | Beberapa satuan ditulis beda-beda di nota tapi maksudnya sama. Semua
    | dibandingkan dalam huruf kecil. Satuan yang tidak terdaftar di sini
    | dipakai apa adanya sebagai kunci ke 'rentang' di bawah.
    |
    */
    'alias' => [
        'ktn' => 'karton',
        'karton' => 'karton',

        'dos' => 'dus',
        'dus' => 'dus',
        'box' => 'dus',
        'kotak' => 'dus',

        'bks' => 'bungkus',
        'bungkus' => 'bungkus',

        'pak' => 'pack',
        'pack' => 'pack',

        'tpl' => 'toples',
        'toples' => 'toples',

        'pc' => 'pcs',
        'pcs' => 'pcs',
        'buah' => 'pcs',

        'btl' => 'botol',
        'botol' => 'botol',

        'lsn' => 'lusin',
        'lusin' => 'lusin',

        'galon' => 'galon',

        'sct' => 'sachet',
        'sachet' => 'sachet',

        'rcg' => 'renceng',
        'renceng' => 'renceng',

        'butir' => 'butir',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rentang markup per satuan (dalam Rupiah)
    |--------------------------------------------------------------------------
    |
    | [minimum, maksimum] kenaikan harga dari harga beli. Harga jual otomatis
    | dipilih di dalam rentang ini, dibulatkan ke kelipatan 'pembulatan' di
    | atas. Silakan disesuaikan sendiri sewaktu-waktu tanpa perlu mengubah
    | kode — cukup ganti angka di sini.
    |
    | Sumber: 'karton' (2.000–3.000) dan 'dus' (2.000–3.500) diminta langsung
    | oleh pemilik toko. Satuan lain diperkirakan proporsional dengan ukuran
    | kemasannya (semakin besar kemasan, semakin lebar rentang markup-nya).
    |
    */
    'rentang' => [
        'pcs' => [200, 500],
        'butir' => [200, 500],
        'sachet' => [200, 500],
        'renceng' => [300, 800],
        'bungkus' => [300, 800],
        'pack' => [500, 1500],
        'botol' => [500, 1500],
        'toples' => [1000, 2000],
        'lusin' => [1000, 2000],
        'galon' => [1500, 3000],
        'karton' => [2000, 3000],
        'dus' => [2000, 3500],

        // Dipakai kalau satuannya tidak ada di daftar atas.
        'default' => [500, 1500],
    ],
];
