<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'password',
        'encrypted_password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'encrypted_password',
        'passkey_hash',
        'passkey_registered_at',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
    ];
}
