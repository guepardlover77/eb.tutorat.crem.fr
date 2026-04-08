<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelloassoToken extends Model
{
    protected $fillable = ['access_token', 'refresh_token', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isValid(): bool
    {
        return $this->expires_at !== null && $this->expires_at->gt(now()->addMinutes(5));
    }
}
