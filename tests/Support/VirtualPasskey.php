<?php

namespace Tests\Support;

use App\Models\User;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use OpenSSLAsymmetricKey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;

/** An ephemeral ES256 authenticator whose signature counter stays at zero. */
class VirtualPasskey
{
    private OpenSSLAsymmetricKey $privateKey;

    private string $credentialId;

    public function __construct()
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

        if ($key === false) {
            throw new RuntimeException('Unable to generate test authenticator key.');
        }

        $this->privateKey = $key;
        $this->credentialId = bin2hex(random_bytes(16));
    }

    public function registration(array $options): array
    {
        $key = openssl_pkey_get_details($this->privateKey)['ec'];
        $coseKey = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($key['x']))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($key['y']));
        $authData = hash('sha256', 'localhost', true)."\x45".pack('N', 0)
            .str_repeat("\0", 16).pack('n', strlen($this->credentialId)).$this->credentialId.$coseKey;
        $attestation = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));

        return $this->credential([
            'clientDataJSON' => Base64UrlSafe::encodeUnpadded($this->clientData('webauthn.create', $options)),
            'attestationObject' => Base64UrlSafe::encodeUnpadded((string) $attestation),
            'transports' => ['internal'],
        ]);
    }

    public function assertion(array $options, User $user): array
    {
        $clientData = $this->clientData('webauthn.get', $options);
        $authData = hash('sha256', 'localhost', true)."\x05".pack('N', 0);

        if (! openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign test assertion.');
        }

        return $this->credential([
            'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientData),
            'authenticatorData' => Base64UrlSafe::encodeUnpadded($authData),
            'signature' => Base64UrlSafe::encodeUnpadded($signature),
            'userHandle' => Base64UrlSafe::encodeUnpadded((string) $user->getAuthIdentifier()),
        ]);
    }

    private function clientData(string $type, array $options): string
    {
        return json_encode([
            'type' => $type,
            'challenge' => $options['challenge'],
            'origin' => 'http://localhost',
            'crossOrigin' => false,
        ], JSON_THROW_ON_ERROR);
    }

    private function credential(array $response): array
    {
        return [
            'id' => Base64UrlSafe::encodeUnpadded($this->credentialId),
            'rawId' => Base64UrlSafe::encodeUnpadded($this->credentialId),
            'type' => 'public-key',
            'response' => $response,
        ];
    }
}
