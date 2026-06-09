<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_hashes_password_without_recoverable_copy(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'username' => 'new-user',
            'password' => 'password123',
            'is_admin' => true,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', 'ユーザーを追加しました');

        $user = User::where('username', 'new-user')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertFalse(Schema::hasColumn('users', 'encrypted_password'));
    }

    public function test_update_hashes_password_without_auth_secret(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createAdmin(isAdmin: false);

        $response = $this->actingAs($admin)->patch(route('users.update', $user), [
            'password' => 'new-password',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', 'ユーザー情報を更新しました');
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_migration_removes_legacy_encrypted_password_column(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('encrypted_password')->nullable();
        });

        $migration = require database_path(
            'migrations/2026_06_09_000000_drop_encrypted_password_from_users_table.php'
        );

        $migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'encrypted_password'));
    }

    private function createAdmin(bool $isAdmin = true): User
    {
        return User::create([
            'username' => 'admin_'.Str::random(8),
            'password' => Hash::make('password'),
            'is_admin' => $isAdmin,
        ]);
    }
}
