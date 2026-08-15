<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['app_id', 'name', 'slug', 'description'];

    public function app()
    {
        return $this->belongsTo(App::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
