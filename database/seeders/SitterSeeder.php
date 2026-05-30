<?php

namespace Database\Seeders;

use App\Models\Sitter;
use Illuminate\Database\Seeder;

class SitterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Sitter::create([
            'name' => 'Budi Santoso',
            'phone' => '081234567891',
            'area' => 'Jakarta Selatan',
            'speciality' => 'Kucing Pemalu & Agresif',
            'status' => 'Active',
        ]);

        Sitter::create([
            'name' => 'Siti Aminah',
            'phone' => '081298765433',
            'area' => 'Jakarta Timur',
            'speciality' => 'Grooming & Kitten Care',
            'status' => 'Active',
        ]);

        Sitter::create([
            'name' => 'Dewi Lestari',
            'phone' => '081387654322',
            'area' => 'Jakarta Barat',
            'speciality' => 'Kucing Senior & Medis',
            'status' => 'Active',
        ]);
    }
}
