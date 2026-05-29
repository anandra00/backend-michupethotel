<?php

namespace Database\Seeders;

use App\Models\SitterPackage;
use Illuminate\Database\Seeder;

class SitterPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SitterPackage::create([
            'name' => '1x Visit/hari',
            'description' => 'Sitter akan berkunjung 1 kali sehari selama 3 jam',
            'price' => 75000,
        ]);

        SitterPackage::create([
            'name' => '2x Visit/hari',
            'description' => 'Sitter akan berkunjung pagi dan sore',
            'price' => 130000,
        ]);
    }
}
