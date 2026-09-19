<?php

namespace Database\Factories;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockBatchFactory extends Factory
{
    public function definition(): array
    {
        $qty = $this->faker->numberBetween(10, 50);

        return [
            'barang_id' => Barang::factory(),
            'tanggal_terima' => now()->toDateString(),
            'tanggal_kedaluwarsa' => null,
            'qty_masuk' => $qty,
            'qty_tersisa' => $qty,
            'harga_beli_satuan' => $this->faker->numberBetween(5000, 50000),
            'supplier' => null,
            'catatan' => null,
        ];
    }

    public function diterima(string $tanggal): self
    {
        return $this->state(fn () => ['tanggal_terima' => $tanggal]);
    }

    public function kedaluwarsa(string $tanggal): self
    {
        return $this->state(fn () => ['tanggal_kedaluwarsa' => $tanggal]);
    }

    public function sisa(int $qty): self
    {
        return $this->state(fn () => ['qty_tersisa' => $qty]);
    }
}
