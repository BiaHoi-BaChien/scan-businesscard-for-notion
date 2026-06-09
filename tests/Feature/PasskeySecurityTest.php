<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PasskeyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class PasskeySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_passkey_authentication_options_are_rate_limited(): void
    {
        $user = $this->createUser();

        $this->mock(PasskeyManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('authenticationOptions')
                ->times(5)
                ->andReturn([
                    'options' => [],
                    'state' => 'test-state',
                ]);
        });

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.30'])
                ->postJson(route('passkeys.options'), [
                    'username' => $user->username,
                ])
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.30'])
            ->postJson(route('passkeys.options'), [
                'username' => $user->username,
            ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_passkey_login_attempts_are_rate_limited(): void
    {
        $user = $this->createUser();

        $this->mock(PasskeyManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('authenticate')
                ->times(5)
                ->andReturnFalse();
        });

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.31'])
                ->postJson(route('passkeys.login'), [
                    'username' => $user->username,
                    'data' => ['id' => 'test-credential'],
                    'state' => 'test-state',
                ])
                ->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.31'])
            ->postJson(route('passkeys.login'), [
                'username' => $user->username,
                'data' => ['id' => 'test-credential'],
                'state' => 'test-state',
            ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_passkey_registration_options_are_rate_limited(): void
    {
        $user = $this->createUser();

        $this->mock(PasskeyManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('registrationOptions')
                ->times(5)
                ->andReturn([
                    'options' => [],
                    'state' => 'test-state',
                ]);
        });

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)
                ->withServerVariables(['REMOTE_ADDR' => '203.0.113.40'])
                ->postJson(route('passkeys.register.options'))
                ->assertOk();
        }

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.40'])
            ->postJson(route('passkeys.register.options'))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_passkey_registration_attempts_are_rate_limited(): void
    {
        $user = $this->createUser();

        $this->mock(PasskeyManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('register')
                ->times(5)
                ->andReturn((object) ['id' => 1]);
        });

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)
                ->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])
                ->postJson(route('passkeys.register'), [
                    'data' => ['id' => 'test-credential'],
                    'state' => 'test-state',
                ])
                ->assertOk();
        }

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.41'])
            ->postJson(route('passkeys.register'), [
                'data' => ['id' => 'test-credential'],
                'state' => 'test-state',
            ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_layout_loads_alpine_from_the_local_vite_bundle(): void
    {
        $response = $this->get(route('login.form'));

        $response->assertOk()
            ->assertDontSee('https://unpkg.com/alpinejs', false)
            ->assertSee('/build/assets/app-', false);
    }

    private function createUser(): User
    {
        return User::create([
            'username' => 'user_'.Str::random(8),
            'password' => Hash::make('password123'),
        ]);
    }
}
