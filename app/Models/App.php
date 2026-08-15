<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class App extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'api_key', 'is_active'];

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_app_role')
                    ->withPivot('role_id')
                    ->withTimestamps();
    }
}
