<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PasskeyManager;
use Cose\Algorithms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
use Mockery\MockInterface;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Uid\Uuid;
use Tests\TestCase;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\TrustPath\EmptyTrustPath;

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

    public function test_passkey_authentication_options_do_not_reveal_whether_username_exists(): void
    {
        $user = $this->createUser();

        $this->mock(PasskeyManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('authenticationOptions')
                ->twice()
                ->andReturn([
                    'options' => ['challenge' => 'test-challenge'],
                    'state' => 'test-state',
                ]);
        });

        $unknownUserResponse = $this->postJson(route('passkeys.options'), [
            'username' => 'unknown-user',
        ]);
        $knownUserResponse = $this->postJson(route('passkeys.options'), [
            'username' => $user->username,
        ]);

        $unknownUserResponse->assertOk();
        $knownUserResponse->assertOk();
        $this->assertSame($knownUserResponse->json(), $unknownUserResponse->json());
    }

    public function test_unknown_username_receives_non_identifying_passkey_options(): void
    {
        $this->postJson(route('passkeys.options'), [
            'username' => 'unknown-user',
        ])->assertOk()
            ->assertJsonStructure([
                'options' => ['challenge'],
                'state',
            ]);
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

    public function test_passkey_login_failure_does_not_reveal_whether_username_exists(): void
    {
        $user = $this->createUser();

        $this->mock(PasskeyManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('authenticate')
                ->once()
                ->andReturnFalse();
        });

        $payload = [
            'data' => ['id' => 'test-credential'],
            'state' => 'test-state',
        ];

        $unknownUserResponse = $this->postJson(route('passkeys.login'), [
            'username' => 'unknown-user',
            ...$payload,
        ]);
        $knownUserResponse = $this->postJson(route('passkeys.login'), [
            'username' => $user->username,
            ...$payload,
        ]);

        $expectedFailure = ['message' => '認証に失敗しました。'];

        $unknownUserResponse->assertUnprocessable()->assertExactJson($expectedFailure);
        $knownUserResponse->assertUnprocessable()->assertExactJson($expectedFailure);
    }

    public function test_valid_passkey_login_still_authenticates_user(): void
    {
        $user = $this->createUser();

        $this->mock(PasskeyManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('authenticate')
                ->once()
                ->andReturnTrue();
        });

        $this->postJson(route('passkeys.login'), [
            'username' => $user->username,
            'data' => ['id' => 'valid-credential'],
            'state' => 'test-state',
        ])->assertOk()
            ->assertJsonPath('redirect', route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_unexpected_json_exception_does_not_expose_internal_message(): void
    {
        $user = $this->createUser();

        $this->mock(PasskeyManager::class, function (MockInterface $mock): void {
            $mock->shouldReceive('authenticationOptions')
                ->once()
                ->andThrow(new LogicException('sensitive internal diagnostic'));
        });

        $this->postJson(route('passkeys.options'), [
            'username' => $user->username,
        ])->assertInternalServerError()
            ->assertExactJson([
                'message' => 'サーバー内部でエラーが発生しました。',
            ]);
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

    public function test_passkey_registration_options_exclude_existing_credentials(): void
    {
        $user = $this->createUser();
        $credentialId = 'existing-credential-id';

        $user->passkeys()->create([
            'name' => 'Existing passkey',
            'data' => $this->createCredentialSource($credentialId),
        ]);

        $this->actingAs($user)
            ->postJson(route('passkeys.register.options'))
            ->assertOk()
            ->assertJsonPath('options.excludeCredentials.0.type', PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY)
            ->assertJsonPath('options.excludeCredentials.0.id', Base64UrlSafe::encodeUnpadded($credentialId));
    }

    public function test_passkey_registration_options_include_supported_public_key_algorithms(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson(route('passkeys.register.options'))
            ->assertOk()
            ->assertJsonPath('options.pubKeyCredParams.0.type', PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY)
            ->assertJsonPath('options.pubKeyCredParams.0.alg', Algorithms::COSE_ALGORITHM_ES256)
            ->assertJsonPath('options.pubKeyCredParams.1.type', PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY)
            ->assertJsonPath('options.pubKeyCredParams.1.alg', Algorithms::COSE_ALGORITHM_RS256);
    }

    public function test_dashboard_hides_registration_form_and_shows_delete_action_when_passkey_exists(): void
    {
        $user = $this->createUser();
        $passkey = $user->passkeys()->create([
            'name' => '自宅PC',
            'data' => $this->createCredentialSource('dashboard-credential-id'),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="passkey-device-name"', false)
            ->assertSee('登録済みパスキー')
            ->assertSee('自宅PC')
            ->assertSee(route('passkeys.destroy', $passkey), false);
    }

    public function test_dashboard_shows_registration_form_when_passkey_does_not_exist(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-passkey-register', false)
            ->assertDontSee('登録済みパスキー');
    }

    public function test_user_can_delete_own_passkey(): void
    {
        $user = $this->createUser();
        $passkey = $user->passkeys()->create([
            'name' => '削除対象',
            'data' => $this->createCredentialSource('deletable-credential-id'),
        ]);

        $this->actingAs($user)
            ->delete(route('passkeys.destroy', $passkey))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'パスキーを削除しました。');

        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    public function test_user_cannot_delete_another_users_passkey(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $passkey = $otherUser->passkeys()->create([
            'name' => '他ユーザーの端末',
            'data' => $this->createCredentialSource('other-user-credential-id'),
        ]);

        $this->actingAs($user)
            ->delete(route('passkeys.destroy', $passkey))
            ->assertNotFound();

        $this->assertDatabaseHas('passkeys', ['id' => $passkey->id]);
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

    private function createCredentialSource(string $credentialId): PublicKeyCredentialSource
    {
        return PublicKeyCredentialSource::fromCredentialRecord(PublicKeyCredentialSource::create(
            $credentialId,
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            [PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_INTERNAL],
            'none',
            EmptyTrustPath::create(),
            Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            base64_decode('pQECAyYgASFYIJV56vRrFusoDf9hm3iDmllcxxXzzKyO9WruKw4kWx7zIlgg/nq63l8IMJcIdKDJcXRh9hoz0L+nVwP1Oxil3/oNQYs=', true),
            'user-handle',
            0,
        ));
    }
}
