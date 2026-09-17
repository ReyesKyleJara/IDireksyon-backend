<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class GovernmentIdSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogFoundationSeeder::class);
    }
}
