<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Support\Config as PasskeyConfig;

class PasskeyManager
{
    private const STATE_LIFETIME_SECONDS = 600;

    public function registrationOptions(User $user): array
    {
        $action = PasskeyConfig::getAction(
            'generate_passkey_register_options',
            GeneratePasskeyRegisterOptionsAction::class
        );

        $optionsJson = $action->execute($user);

        $options = json_decode($optionsJson, true);

        if (! is_array($options)) {
            throw new RuntimeException('パスキー登録オプションの生成に失敗しました。');
        }

        return [
            'options' => $options,
            'state' => $this->issueState($optionsJson, 'passkey.pending.registration', $user),
        ];
    }

    public function register(User $user, array $data, ?string $state = null, ?string $name = null): mixed
    {
        $optionsJson = $this->resolveOptions($state, 'passkey.pending.registration', $user);

        $action = PasskeyConfig::getAction('store_passkey', StorePasskeyAction::class);

        return $action->execute(
            $user,
            json_encode($data),
            $optionsJson,
            request()->getHost(),
            ['name' => $name]
        );
    }

    public function authenticationOptions(): array
    {
        $action = PasskeyConfig::getAction(
            'generate_passkey_authentication_options',
            GeneratePasskeyAuthenticationOptionsAction::class
        );

        $optionsJson = $action->execute();

        // The package flashes its own options; only our pending ceremony is consumed.
        Session::forget('passkey-authentication-options');

        $options = json_decode($optionsJson, true);

        if (! is_array($options)) {
            throw new RuntimeException('パスキー認証オプションの生成に失敗しました。');
        }

        return [
            'options' => $options,
            'state' => $this->issueState($optionsJson, 'passkey.pending.authentication'),
        ];
    }

    public function authenticate(User $user, array $data, ?string $state = null): bool
    {
        $optionsJson = $this->resolveOptions($state, 'passkey.pending.authentication');

        $action = PasskeyConfig::getAction('find_passkey', FindPasskeyToAuthenticateAction::class);

        $passkey = $action->execute(json_encode($data), $optionsJson);

        if (! $passkey) {
            return false;
        }

        $ownerId = $passkey->user_id ?? $passkey->authenticatable_id ?? null;

        if ($ownerId !== $user->id) {
            Log::warning('Passkey authenticate owner mismatch', [
                'user_id' => $user->id,
                'passkey_owner_id' => $ownerId,
            ]);
        }

        return $ownerId === $user->id;
    }

    private function issueState(string $optionsJson, string $sessionKey, ?User $user = null): string
    {
        $state = Str::random(64);

        Session::put($sessionKey, [
            'state' => $state,
            'options' => $optionsJson,
            'expires_at' => now()->getTimestamp() + self::STATE_LIFETIME_SECONDS,
            'user_id' => $user?->getAuthIdentifier(),
        ]);

        return $state;
    }

    private function resolveOptions(?string $state, string $sessionKey, ?User $user = null): string
    {
        $pending = Session::get($sessionKey);

        if (! is_string($state) || $state === '' || ! is_array($pending)
            || ! is_string($pending['state'] ?? null)
            || ! hash_equals($pending['state'], $state)
            || ! is_string($pending['options'] ?? null)
            || ! is_int($pending['expires_at'] ?? null)
            || now()->getTimestamp() >= $pending['expires_at']
            || ($pending['user_id'] ?? null) !== $user?->getAuthIdentifier()) {
            throw new RuntimeException('パスキー認証オプションが無効または期限切れです。もう一度やり直してください。');
        }

        Session::forget($sessionKey);

        // Atomically reject concurrent requests holding the same session snapshot.
        if (! Cache::add('passkey-state-used:'.hash('sha256', $pending['state']), true, self::STATE_LIFETIME_SECONDS)) {
            throw new RuntimeException('このパスキー認証オプションはすでに使用されています。もう一度やり直してください。');
        }

        return $pending['options'];
    }
}
