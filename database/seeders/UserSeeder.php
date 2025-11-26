<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('Default admin seeding has moved to the `user:create-admin` command.');
    }
}
