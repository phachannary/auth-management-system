<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CognitoAppClient extends Model
{
    protected $fillable = [
        'app_id',
        'client_id',
        'client_secret',
        'platform',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function app()
    {
        return $this->belongsTo(App::class);
    }

    /**
     * Find a Cognito app client by client_id
     */
    public static function findByClientId(string $clientId)
    {
        return static::where('client_id', $clientId)
            ->where('is_active', true)
            ->with('app')
            ->first();
    }

    /**
     * Find a Cognito app client by client_id and app slug
     */
    public static function findByClientIdAndAppSlug(string $clientId, string $appSlug)
    {
        return static::where('client_id', $clientId)
            ->where('is_active', true)
            ->whereHas('app', function ($query) use ($appSlug) {
                $query->where('slug', $appSlug);
            })
            ->with('app')
            ->first();
    }
}
