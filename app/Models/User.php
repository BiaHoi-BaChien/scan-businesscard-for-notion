<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laragear\WebAuthn\WebAuthnData;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class User extends Authenticatable implements WebAuthnAuthenticatable
{
    use HasFactory, Notifiable, WebAuthnAuthentication;

    protected $fillable = [
        'username',
        'password',
        'encrypted_password',
        'is_admin',
        'passkey_hash',
        'passkey_registered_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'passkey_hash',
        'encrypted_password',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'passkey_registered_at' => 'datetime',
    ];

    public function hasPasskey(): bool
    {
        try {
            return $this->webAuthnCredentials()->exists();
        } catch (\Throwable $e) {
            return ! empty($this->passkey_hash);
        }
    }

    public function webAuthnData(): WebAuthnData
    {
        // WebAuthn requires a stable, per-user identifier; using APP_URL here would
        // cause all users to share the same handle and break passkey validation.
        return WebAuthnData::make($this->username, $this->username);
    }

    public function webAuthnId(): UuidInterface
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'user:'.$this->getAuthIdentifier());
    }
}
