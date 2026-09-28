<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user:create-admin {--username=} {--password=}';

    /**
     * The console command description.
     */
    protected $description = 'Create or update an admin user with interactive prompts.';

    public function handle(): int
    {
        $username = $this->option('username') ?? $this->ask('Admin username');

        if (! is_string($username) || trim($username) === '') {
            $this->error('Username is required.');

            return self::FAILURE;
        }

        $username = trim($username);

        $password = $this->option('password') ?? $this->secret('Admin password (input hidden)');

        if (! is_string($password) || $password === '') {
            $this->error('Password is required.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['username' => $username]);
        $user->password = Hash::make($password);
        $user->is_admin = true;
        $user->setRememberToken(Str::random(60));
        $user->save();

        $this->info(sprintf('Admin user "%s" has been created or updated.', $username));

        return self::SUCCESS;
    }
}
