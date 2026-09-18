<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rincian batch mana saja yang dipakai untuk memenuhi satu baris
     * detail transaksi penjualan (satu detail transaksi bisa terpenuhi
     * dari lebih dari satu batch apabila batch pertama tidak cukup).
     * Tabel ini adalah bukti nyata alur konsumsi FIFO dan dipakai untuk
     * menghitung HPP (harga pokok penjualan) yang akurat per batch.
     */
    public function up(): void
    {
        Schema::create('detail_transaksi_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detail_transaksi_id')->constrained('detail_transaksis')->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->constrained('stock_batches')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('harga_beli_satuan');
            $table->timestamps();

            $table->index('detail_transaksi_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_transaksi_batches');
    }
};
