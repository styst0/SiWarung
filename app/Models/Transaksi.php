<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaksi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transaksis';

    protected $fillable = [
        'kode_transaksi',
        'pelanggan',
        'total_harga',
        'total_bayar',
        'kembalian',
        'status',
        'catatan',
        'user_id',
    ];

    protected $casts = [
        'total_harga' => 'integer',
        'total_bayar' => 'integer',
        'kembalian' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($transaksi) {
            if (empty($transaksi->kode_transaksi)) {
                $transaksi->kode_transaksi = static::generateKodeTransaksi();
            }
        });
    }

    /**
     * Membuat kode transaksi baru berdasarkan jumlah transaksi hari ini.
     *
     * Nomor urutnya bisa bentrok kalau dua transaksi dibuat nyaris
     * bersamaan, karena dua permintaan bisa membaca hitungan yang sama
     * sebelum salah satunya selesai disimpan. Kolom kode_transaksi
     * memiliki batasan unik di basis data untuk mencegah duplikat;
     * TransaksiController mengulang seluruh proses pembuatan transaksi
     * kalau bentrokan itu terjadi, sehingga percobaan berikutnya
     * menghitung ulang dari jumlah transaksi yang sudah tersimpan.
     */
    public static function generateKodeTransaksi(): string
    {
        $latest = static::whereDate('created_at', today())->count() + 1;

        return 'TRX-'.now()->format('ymd').'-'.str_pad($latest, 3, '0', STR_PAD_LEFT);
    }

    public function detailTransaksi()
    {
        return $this->hasMany(DetailTransaksi::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
