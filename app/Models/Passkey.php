<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\LaravelPasskeys\Support\Serializer;
use Spatie\LaravelPasskeys\Models\Passkey as BasePasskey;
use Webauthn\PublicKeyCredentialSource;

class Passkey extends BasePasskey
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function casts(): array
    {
        return array_merge(parent::casts(), [
            'transports' => 'array',
        ]);
    }

    public function data(): Attribute
    {
        $serializer = Serializer::make();

        return new Attribute(
            get: fn (string $value) => $serializer->fromJson(
                $value,
                PublicKeyCredentialSource::class
            ),
            set: function (PublicKeyCredentialSource $value) use ($serializer) {
                return [
                    'credential_id' => mb_convert_encoding($value->publicKeyCredentialId, 'UTF-8'),
                    'data' => $serializer->toJson($value),
                    'public_key' => base64_encode($value->credentialPublicKey),
                    'counter' => $value->counter,
                    'attestation_type' => $value->attestationType,
                    'aaguid' => (string) $value->aaguid,
                    'transports' => json_encode($value->transports),
                ];
            },
        );
    }
}
