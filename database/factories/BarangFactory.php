<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BarangFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode_barang' => 'SKU-'.$this->faker->unique()->numerify('#####'),
            'nama_barang' => $this->faker->words(3, true),
            'kategori' => $this->faker->randomElement(['Sembako', 'Minuman', 'Snack']),
            'satuan' => $this->faker->randomElement(['pcs', 'ktn', 'dos']),
            'harga_beli' => $this->faker->numberBetween(5000, 50000),
            'harga_jual' => $this->faker->numberBetween(6000, 60000),
            'stok' => 0,
            'stok_minimum' => 5,
            'deskripsi' => null,
        ];
    }
}
