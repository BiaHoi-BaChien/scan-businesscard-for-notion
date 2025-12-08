<?php

namespace App\Services;

use App\Models\User;
use BadMethodCallException;
use RuntimeException;

class PasskeyManager
{
    private const FACADE_CLASS = '\\Spatie\\Passkey\\Facades\\Passkey';

    private function ensureAvailable(): void
    {
        if (! class_exists(self::FACADE_CLASS)) {
            throw new RuntimeException('spatie/laravel-passkeys がインストールされていません。composer install を実行してください。');
        }
    }

    private function callFirstAvailable(array $methods, array $parameters = [])
    {
        $this->ensureAvailable();

        foreach ($methods as $method) {
            try {
                $result = forward_static_call_array([self::FACADE_CLASS, $method], $parameters);

                if (is_object($result) && method_exists($result, 'toArray')) {
                    return $result->toArray();
                }

                return $result;
            } catch (BadMethodCallException $exception) {
                continue;
            }
        }

        throw new RuntimeException('spatie/laravel-passkeys の公開メソッドが見つかりません。パッケージのバージョンを確認してください。');
    }

    public function registrationOptions(User $user): array
    {
        return (array) $this->callFirstAvailable([
            'beginRegistration',
            'registration',
            'prepareRegistration',
            'createRegistrationOptions',
            'generateRegistrationData',
        ], [$user]);
    }

    public function register(User $user, array $data, ?string $name = null): mixed
    {
        return $this->callFirstAvailable([
            'finishRegistration',
            'create',
            'store',
            'confirmRegistration',
            'register',
        ], [$user, $data, $name]);
    }

    public function authenticationOptions(User $user): array
    {
        return (array) $this->callFirstAvailable([
            'beginAuthentication',
            'authentication',
            'prepareAuthentication',
            'createAuthenticationOptions',
            'generateAuthenticationData',
            'request',
        ], [$user]);
    }

    public function authenticate(User $user, array $data): bool
    {
        return (bool) $this->callFirstAvailable([
            'finishAuthentication',
            'authenticate',
            'confirmAuthentication',
            'login',
        ], [$user, $data]);
    }
}
