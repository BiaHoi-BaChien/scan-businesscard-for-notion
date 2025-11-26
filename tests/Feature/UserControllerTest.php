<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_requires_auth_secret(): void
    {
        $this->setAuthSecret(null);
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'username' => 'new-user',
            'password' => 'password123',
            'is_admin' => true,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('auth_secret');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_store_succeeds_when_auth_secret_is_present(): void
    {
        $this->setAuthSecret('test-secret');
        $admin = $this->createAdmin('test-secret');

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'username' => 'new-user',
            'password' => 'password123',
            'is_admin' => true,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', 'ユーザーを追加しました');

        $user = User::where('username', 'new-user')->first();

        $this->assertNotNull($user);
        $this->assertSame(
            base64_encode(openssl_encrypt(
                'password123',
                'AES-256-CBC',
                hash('sha256', 'test-secret'),
                0,
                substr(hash('sha256', 'test-secret'), 0, 16)
            )),
            $user->encrypted_password
        );
    }

    public function test_update_requires_auth_secret_when_password_is_changed(): void
    {
        $this->setAuthSecret(null);
        $admin = $this->createAdmin();
        $user = $this->createAdmin(isAdmin: false);

        $response = $this->actingAs($admin)->patch(route('users.update', $user), [
            'password' => 'new-password',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('auth_secret');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_update_encrypts_password_when_secret_is_available(): void
    {
        $this->setAuthSecret('test-secret');
        $admin = $this->createAdmin('test-secret');
        $user = $this->createAdmin('test-secret', false);

        $response = $this->actingAs($admin)->patch(route('users.update', $user), [
            'password' => 'updated-password',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', 'ユーザー情報を更新しました');

        $this->assertTrue(Hash::check('updated-password', $user->fresh()->password));
        $this->assertSame(
            base64_encode(openssl_encrypt(
                'updated-password',
                'AES-256-CBC',
                hash('sha256', 'test-secret'),
                0,
                substr(hash('sha256', 'test-secret'), 0, 16)
            )),
            $user->fresh()->encrypted_password
        );
    }

    private function createAdmin(?string $secret = 'seed-secret', bool $isAdmin = true): User
    {
        return User::create([
            'username' => 'admin_'.Str::random(8),
            'password' => Hash::make('password'),
            'encrypted_password' => base64_encode(openssl_encrypt(
                'password',
                'AES-256-CBC',
                hash('sha256', $secret),
                0,
                substr(hash('sha256', $secret), 0, 16)
            )),
            'is_admin' => $isAdmin,
        ]);
    }

    private function setAuthSecret(?string $value): void
    {
        if ($value === null) {
            putenv('AUTH_SECRET');
            unset($_ENV['AUTH_SECRET'], $_SERVER['AUTH_SECRET']);
            return;
        }

        putenv('AUTH_SECRET='.$value);
        $_ENV['AUTH_SECRET'] = $value;
        $_SERVER['AUTH_SECRET'] = $value;
    }
}
