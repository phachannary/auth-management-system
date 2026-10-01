<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OAuthAccessToken extends Model
{
    use HasFactory;

    protected $table = 'oauth_access_tokens';

    protected $fillable = [
        'user_id',
        'client_id',
        'token',
        'expires_at',
        'scopes',
        'revoked',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'scopes' => 'array',
        'revoked' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function refreshToken()
    {
        return $this->hasOne(OAuthRefreshToken::class, 'access_token_id');
    }
}
