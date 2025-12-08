<?php

namespace App\Models;

use App\Models\Passkey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    protected $casts = [
        'is_admin' => 'boolean',
    ];

    public function passkeys(): HasMany
    {
        return $this->hasMany(Passkey::class);
    }
}
