<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Staff::create([
            'name' => 'ali',
            'email' => 'ali2@gmail.com',
            'password' => '12345678',
        ]);
    }
}
