<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'facebook_id',
        'google_id',
        'cognito_sub',
        'cognito_username',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function appRoles()
    {
        return $this->belongsToMany(App::class, 'user_app_role')
                    ->withPivot('role_id')
                    ->withTimestamps();
    }

    public function rolesForApp($appSlug)
    {
        return Role::whereIn('id',
            \DB::table('user_app_role')
                ->join('apps', 'apps.id', '=', 'user_app_role.app_id')
                ->where('user_app_role.user_id', $this->id)
                ->where('apps.slug', $appSlug)
                ->pluck('user_app_role.role_id')
        )->get();
    }

    public function hasRoleInApp($roleSlug, $appSlug)
    {
        return $this->rolesForApp($appSlug)->contains('slug', $roleSlug);
    }
}
