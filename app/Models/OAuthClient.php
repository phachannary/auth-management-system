<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OAuthClient extends Model
{
    use HasFactory;

    protected $table = 'oauth_clients';

    protected $fillable = [
        'name',
        'client_id',
        'client_secret',
        'redirect_uris',
        'scopes',
        'confidential',
        'active',
    ];

    protected $casts = [
        'redirect_uris' => 'array',
        'scopes' => 'array',
        'confidential' => 'boolean',
        'active' => 'boolean',
    ];

    public function authCodes()
    {
        return $this->hasMany(OAuthAuthCode::class, 'client_id', 'client_id');
    }

    public function accessTokens()
    {
        return $this->hasMany(OAuthAccessToken::class, 'client_id', 'client_id');
    }

    public function refreshTokens()
    {
        return $this->hasMany(OAuthRefreshToken::class, 'client_id', 'client_id');
    }
}
