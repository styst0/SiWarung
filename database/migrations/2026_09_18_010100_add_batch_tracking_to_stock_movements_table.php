<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('stock_batch_id')->nullable()->after('barang_id')
                ->constrained('stock_batches')->nullOnDelete();
            $table->string('reason', 30)->default('penyesuaian')->after('direction');
            $table->unsignedBigInteger('harga_satuan')->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_batch_id');
            $table->dropColumn(['reason', 'harga_satuan']);
        });
    }
};
