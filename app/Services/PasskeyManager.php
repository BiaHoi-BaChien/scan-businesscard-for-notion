<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Support\Config as PasskeyConfig;

class PasskeyManager
{
    public function registrationOptions(User $user): array
    {
        $action = PasskeyConfig::getAction(
            'generate_passkey_register_options',
            GeneratePasskeyRegisterOptionsAction::class
        );

        $optionsJson = $action->execute($user);

        Session::put('passkey-registration-options', $optionsJson);

        $options = json_decode($optionsJson, true);

        if (! is_array($options)) {
            throw new RuntimeException('パスキー登録オプションの生成に失敗しました。');
        }

        return [
            'options' => $options,
            'state' => $this->encryptOptions($optionsJson),
        ];
    }

    public function register(User $user, array $data, ?string $state = null, ?string $name = null): mixed
    {
        $optionsJson = $this->resolveOptions($state, 'passkey-registration-options');

        $action = PasskeyConfig::getAction('store_passkey', StorePasskeyAction::class);

        return $action->execute(
            $user,
            json_encode($data),
            $optionsJson,
            request()->getHost(),
            ['name' => $name]
        );
    }

    public function authenticationOptions(User $user): array
    {
        $action = PasskeyConfig::getAction(
            'generate_passkey_authentication_options',
            GeneratePasskeyAuthenticationOptionsAction::class
        );

        $optionsJson = $action->execute();

        Session::put('passkey-authentication-options', $optionsJson);

        $options = json_decode($optionsJson, true);

        if (! is_array($options)) {
            throw new RuntimeException('パスキー認証オプションの生成に失敗しました。');
        }

        return [
            'options' => $options,
            'state' => $this->encryptOptions($optionsJson),
        ];
    }

    public function authenticate(User $user, array $data, ?string $state = null): bool
    {
        $optionsJson = $this->resolveOptions($state, 'passkey-authentication-options');

        $action = PasskeyConfig::getAction('find_passkey', FindPasskeyToAuthenticateAction::class);

        $passkey = $action->execute(json_encode($data), $optionsJson);

        Log::debug('Passkey authenticate lookup result', [
            'user_id' => $user->id,
            'passkey_found' => (bool) $passkey,
            'passkey_class' => $passkey ? get_class($passkey) : null,
        ]);

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

    private function encryptOptions(string $optionsJson): string
    {
        return Crypt::encryptString($optionsJson);
    }

    private function resolveOptions(?string $state, string $sessionKey): string
    {
        if (is_string($state) && $state !== '') {
            $cacheKey = $this->stateCacheKey($state);
            $timestamp = Carbon::now();
            $cacheAdded = Cache::add($cacheKey, $timestamp->toIso8601String(), $timestamp->copy()->addMinutes(10));

            Log::debug('Passkey resolveOptions using encrypted state', [
                'method' => 'state',
                'state_cache_key' => $cacheKey,
                'state_cache_timestamp' => $timestamp->toIso8601String(),
                'cache_added' => $cacheAdded,
                'existing_state_timestamp' => $cacheAdded ? null : Cache::get($cacheKey),
            ]);

            if (! $cacheAdded) {
                throw new RuntimeException('このパスキー認証オプションはすでに使用されています。もう一度やり直してください。');
            }

            try {
                return Crypt::decryptString($state);
            } catch (\Throwable $exception) {
                Log::debug('Passkey resolveOptions state decrypt failed', [
                    'method' => 'state',
                    'state_cache_key' => $cacheKey,
                    'state_cache_timestamp' => $timestamp->toIso8601String(),
                    'exception_class' => get_class($exception),
                    'exception_message' => $exception->getMessage(),
                ]);

                throw new RuntimeException('パスキー認証オプションが復号できません。', 0, $exception);
            }
        }

        $optionsJson = Session::pull($sessionKey);

        Log::debug('Passkey resolveOptions using session', [
            'method' => 'session',
            'session_key' => $sessionKey,
            'session_id' => Session::getId(),
            'options_available' => is_string($optionsJson) && $optionsJson !== '',
        ]);

        if (! is_string($optionsJson) || $optionsJson === '') {
            throw new RuntimeException('パスキー認証オプションがセッションにありません。もう一度やり直してください。');
        }

        return $optionsJson;
    }

    private function stateCacheKey(string $state): string
    {
        return 'passkey-state-used:' . hash('sha256', $state);
    }
}
