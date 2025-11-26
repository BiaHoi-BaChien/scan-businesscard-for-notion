<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CheckUserPassword extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user:check-password {username : Username of the account} {password? : Password to validate (leave empty to be prompted)}';

    /**
     * The console command description.
     */
    protected $description = 'Check whether a provided password matches the stored hash for the user.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = $this->argument('username');
        $password = $this->argument('password');

        if ($password === null || $password === '') {
            $password = $this->secret('Password (input hidden): ');
        }

        if (! is_string($password) || $password === '') {
            $this->error('Password is required.');

            return self::FAILURE;
        }

        $user = User::where('username', $username)->select(['id', 'username', 'password'])->first();

        if (! $user) {
            $this->error(sprintf('User "%s" not found.', $username));

            return self::FAILURE;
        }

        $match = Hash::check($password, $user->password);

        $this->info(sprintf('User: %s (id: %s)', $user->username, $user->id));
        $this->line('Password match: ' . ($match ? '<fg=green>true</>' : '<fg=red>false</>'));

        return $match ? self::SUCCESS : self::FAILURE;
    }
}
