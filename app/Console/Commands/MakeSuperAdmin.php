<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class MakeSuperAdmin extends Command
{
    protected $signature = 'cms:make-super-admin {username : Existing username, or username for a new account}';

    protected $description = 'Set up a Super Admin locally without a default or hardcoded password';

    public function handle(): int
    {
        $username = strtolower(trim($this->argument('username')));
        if (Validator::make(['username' => $username], ['username' => 'required|string|max:80|regex:/^[a-z0-9_.-]+$/'])->fails()) {
            $this->error('Use letters, numbers, dots, hyphens or underscores for the username.');

            return self::FAILURE;
        }
        $user = User::where('username', $username)->first();
        if (! $user) {
            if (! $this->input->isInteractive()) {
                $this->error('Account not found. Run interactively to choose a name and private password.');

                return self::FAILURE;
            }
            $data = ['name' => $this->ask('Your name'), 'email' => $this->ask('Contact email'), 'password' => $this->secret('Password (at least 12 characters)'), 'password_confirmation' => $this->secret('Confirm password')];
            $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => ['required', 'confirmed', Password::min(12)]]);
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'username' => $username, 'password' => $data['password']]);
        }
        $user->role = 'super_admin';
        $user->is_active = true;
        $user->save();
        $this->info('Super Admin access enabled. Sign in with this account at /login, then open /admin.');

        return self::SUCCESS;
    }
}
