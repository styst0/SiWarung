<?php

namespace Database\Factories;

use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaksi>
 */
class TransaksiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = $this->faker->numberBetween(10000, 200000);

        return [
            'pelanggan' => $this->faker->optional()->name(),
            'total_harga' => $total,
            'total_bayar' => $total,
            'kembalian' => 0,
            'status' => 'lunas',
            'catatan' => null,
            'user_id' => User::factory(),
        ];
    }
}
