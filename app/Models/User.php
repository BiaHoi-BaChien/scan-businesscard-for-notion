<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
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

    public function businessCards()
    {
        return $this->hasMany(BusinessCard::class);
    }

    public function hasPasskey(): bool
    {
        return ! empty($this->passkey_hash);
    }
}
