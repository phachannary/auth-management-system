<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;

class RoleApiController extends Controller
{
    public function index(Request $request)
    {
        $appSlug = $request->attributes->get('app_slug');

        if (!$appSlug) {
            return response()->json(['error' => 'Application context not found'], 500);
        }

        $roles = Role::whereHas('app', function ($query) use ($appSlug) {
            $query->where('slug', $appSlug);
        })
        ->with('permissions')
        ->get();

        return response()->json(['roles' => $roles]);
    }

    public function show(Request $request, $id)
    {
        $role = Role::with('permissions', 'app')
            ->findOrFail($id);

        return response()->json(['role' => $role]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'app_slug' => 'required|string',
            'name' => 'required|string',
            'slug' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $app = \App\Models\App::where('slug', $request->app_slug)->firstOrFail();

        $role = Role::create([
            'app_id' => $app->id,
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
        ]);

        return response()->json(['role' => $role], 201);
    }

    public function assignPermission(Request $request, $roleId)
    {
        $request->validate([
            'permission_slug' => 'required|string',
        ]);

        $role = Role::findOrFail($roleId);
        $permission = Permission::where('slug', $request->permission_slug)
            ->where('app_id', $role->app_id)
            ->firstOrFail();

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        return response()->json(['message' => 'Permission assigned to role']);
    }

    public function removePermission(Request $request, $roleId)
    {
        $request->validate([
            'permission_slug' => 'required|string',
        ]);

        $role = Role::findOrFail($roleId);
        $permission = Permission::where('slug', $request->permission_slug)
            ->where('app_id', $role->app_id)
            ->firstOrFail();

        $role->permissions()->detach($permission->id);

        return response()->json(['message' => 'Permission removed from role']);
    }
}
