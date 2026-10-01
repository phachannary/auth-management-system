<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OAuthAuthCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_id',
        'code',
        'expires_at',
        'redirect_uri',
        'scopes',
        'state',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'scopes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
