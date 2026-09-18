<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $table = 'stock_movements';

    protected $fillable = [
        'barang_id',
        'stock_batch_id',
        'direction',
        'reason',
        'qty',
        'harga_satuan',
        'reference_type',
        'reference_id',
        'notes',
    ];

    protected $casts = [
        'qty' => 'integer',
        'harga_satuan' => 'integer',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }

    public function stockBatch()
    {
        return $this->belongsTo(StockBatch::class);
    }
}
