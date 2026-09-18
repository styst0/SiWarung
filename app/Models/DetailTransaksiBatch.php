<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailTransaksiBatch extends Model
{
    protected $table = 'detail_transaksi_batches';

    protected $fillable = [
        'detail_transaksi_id',
        'stock_batch_id',
        'qty',
        'harga_beli_satuan',
    ];

    protected $casts = [
        'qty' => 'integer',
        'harga_beli_satuan' => 'integer',
    ];

    public function detailTransaksi()
    {
        return $this->belongsTo(DetailTransaksi::class);
    }

    public function stockBatch()
    {
        return $this->belongsTo(StockBatch::class);
    }
}
