<?php

namespace App\Services;

use App\Models\User;
use BadMethodCallException;
use Composer\InstalledVersions;
use RuntimeException;

class PasskeyManager
{
    private const FACADE_CLASSES = [
        '\\Spatie\\Passkey\\Facades\\Passkey',
        '\\Spatie\\Passkeys\\Facades\\Passkey',
    ];

    private function ensureInstalled(): void
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('spatie/laravel-passkeys')) {
            return;
        }

        throw new RuntimeException('spatie/laravel-passkeys がインストールされていません。composer install を実行してください。');
    }

    private function resolveFacadeClass(): string
    {
        $this->ensureInstalled();

        foreach (self::FACADE_CLASSES as $facadeClass) {
            if (class_exists($facadeClass)) {
                return $facadeClass;
            }
        }

        throw new RuntimeException('spatie/laravel-passkeys の公開メソッドが見つかりません。パッケージのバージョンを確認してください。');
    }

    private function callFirstAvailable(array $methods, array $parameters = [])
    {
        $facadeClass = $this->resolveFacadeClass();

        foreach ($methods as $method) {
            try {
                $result = forward_static_call_array([$facadeClass, $method], $parameters);

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
            'registrationOptions',
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
            'validate',
        ], [$user, $data, $name]);
    }

    public function authenticationOptions(User $user): array
    {
        return (array) $this->callFirstAvailable([
            'beginAuthentication',
            'authentication',
            'authenticationOptions',
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
