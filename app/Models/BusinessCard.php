<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'front_path',
        'back_path',
        'analysis',
    ];

    protected $casts = [
        'analysis' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
