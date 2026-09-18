<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel batch penerimaan barang — dasar dari implementasi FIFO.
     * Setiap kali barang diterima (stok masuk), satu baris batch baru
     * dibuat dengan tanggal terima, tanggal kedaluwarsa, dan sisa qty-nya
     * sendiri. Saat penjualan terjadi, stok dikonsumsi dari batch dengan
     * tanggal_terima paling lama terlebih dahulu (First In First Out).
     */
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barangs')->restrictOnDelete();
            $table->string('kode_batch', 30)->unique();
            $table->date('tanggal_terima');
            $table->date('tanggal_kedaluwarsa')->nullable();
            $table->unsignedInteger('qty_masuk');
            $table->unsignedInteger('qty_tersisa');
            $table->unsignedBigInteger('harga_beli_satuan');
            $table->string('supplier', 100)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['barang_id', 'tanggal_terima'], 'stock_batches_barang_fifo_idx');
            $table->index('tanggal_kedaluwarsa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
