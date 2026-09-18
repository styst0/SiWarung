<?php

namespace Database\Factories;

use App\Models\Barang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barang>
 */
class BarangFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
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
