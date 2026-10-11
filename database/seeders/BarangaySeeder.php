<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarangaySeeder extends Seeder
{
    public function run(): void
    {
        $barangays = [
            'Bagbaguin',
            'Balasing',
            'Buenavista',
            'Bulac',
            'Camangyanan',
            'Catmon',
            'Cay Pombo',
            'Caysio',
            'Guyong',
            'Lalakhan',
            'Mag-asawang Sapa',
            'Mahabang Parang',
            'Manggahan',
            'Parada',
            'Poblacion',
            'Pulong Buhangin',
            'San Gabriel',
            'San Jose Patag',
            'San Vicente',
            'Santa Clara',
            'Santa Cruz',
            'Silangan',
            'Tabing Bakod (Santo Tomas)',
            'Tumana',
        ];

        foreach ($barangays as $barangay) {
            DB::table('barangays')->updateOrInsert(
                ['name' => $barangay],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}