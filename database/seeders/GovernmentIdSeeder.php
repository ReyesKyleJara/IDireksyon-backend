<?php

namespace Database\Seeders;

use App\Models\GovernmentId;
use Illuminate\Database\Seeder;

class GovernmentIdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GovernmentId::create([
            'name' => 'Philippine Passport',
            'agency' => 'Department of Foreign Affairs (DFA)',
            'purpose' => 'Proof of identity and citizenship for international travel.',
            'validity' => '10 years for adults',
            'description' => 'A government-issued travel document for Filipino citizens.',
            'last_updated' => now(),
        ]);

        GovernmentId::create([
            'name' => 'PhilSys ID',
            'agency' => 'Philippine Statistics Authority (PSA)',
            'purpose' => 'Proof of identity for transactions and access to government and private services.',
            'validity' => 'No expiration',
            'description' => 'The official national identification card of the Philippines.',
            'last_updated' => now(),
        ]);

        GovernmentId::create([
            'name' => 'PhilHealth ID',
            'agency' => 'Philippine Health Insurance Corporation (PhilHealth)',
            'purpose' => 'Identification for PhilHealth membership and related services.',
            'validity' => 'No expiration',
            'description' => 'An identification document associated with PhilHealth membership.',
            'last_updated' => now(),
        ]);
    }
}