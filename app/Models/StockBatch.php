<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockBatch extends Model
{
    use HasFactory;

    protected $table = 'stock_batches';

    protected $fillable = [
        'barang_id',
        'kode_batch',
        'tanggal_terima',
        'tanggal_kedaluwarsa',
        'qty_masuk',
        'qty_tersisa',
        'harga_beli_satuan',
        'supplier',
        'catatan',
    ];

    protected $casts = [
        'tanggal_terima' => 'date',
        'tanggal_kedaluwarsa' => 'date',
        'qty_masuk' => 'integer',
        'qty_tersisa' => 'integer',
        'harga_beli_satuan' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StockBatch $batch) {
            if (empty($batch->kode_batch)) {
                $latest = static::whereDate('created_at', today())->count() + 1;
                $batch->kode_batch = 'BTC-'.now()->format('ymd').'-'.str_pad((string) $latest, 3, '0', STR_PAD_LEFT);
            }

            if (empty($batch->tanggal_terima)) {
                $batch->tanggal_terima = now()->toDateString();
            }

            if (! isset($batch->qty_tersisa)) {
                $batch->qty_tersisa = $batch->qty_masuk;
            }
        });
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function detailTransaksiBatches()
    {
        return $this->hasMany(DetailTransaksiBatch::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('qty_tersisa', '>', 0);
    }

    public function scopeUrutanFifo(Builder $query): Builder
    {
        return $query->orderBy('tanggal_terima')->orderBy('id');
    }

    public function scopeSudahKedaluwarsa(Builder $query): Builder
    {
        return $query->whereNotNull('tanggal_kedaluwarsa')
            ->whereDate('tanggal_kedaluwarsa', '<', now()->toDateString());
    }

    public function scopeAkanKedaluwarsa(Builder $query, int $hari = 7): Builder
    {
        return $query->whereNotNull('tanggal_kedaluwarsa')
            ->whereDate('tanggal_kedaluwarsa', '>=', now()->toDateString())
            ->whereDate('tanggal_kedaluwarsa', '<=', now()->addDays($hari)->toDateString());
    }

    public function getStatusKedaluwarsaAttribute(): string
    {
        if (! $this->tanggal_kedaluwarsa) {
            return 'tidak_ada';
        }

        $today = now()->toDateString();
        $tanggal = $this->tanggal_kedaluwarsa->toDateString();

        if ($tanggal < $today) {
            return 'kedaluwarsa';
        }

        if ($tanggal <= now()->addDays(7)->toDateString()) {
            return 'segera';
        }

        return 'aman';
    }
}
