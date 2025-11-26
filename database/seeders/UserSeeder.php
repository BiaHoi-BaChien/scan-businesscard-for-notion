<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('DEFAULT_ADMIN_USERNAME', 'admin');
        $password = env('DEFAULT_ADMIN_PASSWORD', 'password');
        $secret = env('AUTH_SECRET', env('APP_KEY'));

        if (! $secret) {
            $this->command?->warn('AUTH_SECRET is not set; skipping admin seeding.');
            return;
        }

        User::updateOrCreate(
            ['username' => $username],
            [
                'password' => Hash::make($password),
                'encrypted_password' => base64_encode(openssl_encrypt($password, 'AES-256-CBC', hash('sha256', $secret), 0, substr(hash('sha256', $secret), 0, 16))),
                'is_admin' => true,
            ]
        );
    }
}
