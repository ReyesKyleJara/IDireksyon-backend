<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LocalSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('The development Super Admin can only be seeded locally.');
        }
        $user = User::where('username', 'veryveryadmin')->first()
            ?? User::where('email', 'veryveryadmin@idireksyon.test')->first()
            ?? new User(['name' => 'System Administrator', 'email' => 'veryveryadmin@idireksyon.test']);
        $user->username = 'VeryVeryAdmin';
        $user->role = 'super_admin';
        $user->is_active = true;
        $user->password = Hash::make('SPAdmin1234');
        $user->remember_token = Str::random(60);
        $user->save();
    }
}
