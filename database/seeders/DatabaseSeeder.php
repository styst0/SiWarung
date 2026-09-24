<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'tokoratih@gmail.com'],
            ['name' => 'Ratih', 'password' => Hash::make('QkePGN5w6P#W8')]
        );
    }
}
