<?php

namespace App\Services;

use App\Models\User;
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

        return $options;
    }

    public function register(User $user, array $data, ?string $name = null): mixed
    {
        $optionsJson = Session::pull('passkey-registration-options');

        if (! is_string($optionsJson) || $optionsJson === '') {
            throw new RuntimeException('パスキー登録オプションがセッションにありません。もう一度やり直してください。');
        }

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

        return $options;
    }

    public function authenticate(User $user, array $data): bool
    {
        $optionsJson = Session::pull('passkey-authentication-options');

        if (! is_string($optionsJson) || $optionsJson === '') {
            throw new RuntimeException('パスキー認証オプションがセッションにありません。もう一度やり直してください。');
        }

        $action = PasskeyConfig::getAction('find_passkey', FindPasskeyToAuthenticateAction::class);

        $passkey = $action->execute(json_encode($data), $optionsJson);

        if (! $passkey) {
            return false;
        }

        $ownerId = $passkey->user_id ?? $passkey->authenticatable_id ?? null;

        return $ownerId === $user->id;
    }
}
