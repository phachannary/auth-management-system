<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['app_id', 'name', 'slug', 'description'];

    public function app()
    {
        return $this->belongsTo(App::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_app_role')
                    ->withTimestamps();
    }
}
