<?php

namespace App\Actions;

use Cose\Algorithms;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Support\Serializer;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialSource;

class GeneratePasskeyRegisterOptionsAction extends \Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction
{
    public function execute(
        HasPasskeys $authenticatable,
        bool $asJson = true,
    ): string|PublicKeyCredentialCreationOptions {
        $options = parent::execute($authenticatable, false);

        $options->pubKeyCredParams = [
            PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256),
            PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_RS256),
        ];

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
