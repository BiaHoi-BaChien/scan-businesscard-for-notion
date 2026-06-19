<?php

namespace App\Actions;

use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Support\Serializer;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSource;

class GeneratePasskeyRegisterOptionsAction extends \Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction
{
    public function execute(
        HasPasskeys $authenticatable,
        bool $asJson = true,
    ): string|PublicKeyCredentialCreationOptions {
        $options = parent::execute($authenticatable, false);

        $options->excludeCredentials = $authenticatable->passkeys()
            ->get()
            ->pluck('data')
            ->filter(fn (mixed $credential): bool => $credential instanceof PublicKeyCredentialSource)
            ->map(fn (PublicKeyCredentialSource $credential) => $credential->getPublicKeyCredentialDescriptor())
            ->values()
            ->all();

        return $asJson ? Serializer::make()->toJson($options) : $options;
    }
}
