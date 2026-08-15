<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionApiController extends Controller
{
    public function index(Request $request)
    {
        $appSlug = $request->attributes->get('app_slug');

        if (!$appSlug) {
            return response()->json(['error' => 'Application context not found'], 500);
        }

        $permissions = Permission::whereHas('app', function ($query) use ($appSlug) {
            $query->where('slug', $appSlug);
        })
        ->with('roles')
        ->get();

        return response()->json(['permissions' => $permissions]);
    }

    public function show(Request $request, $id)
    {
        $permission = Permission::with('roles', 'app')
            ->findOrFail($id);

        return response()->json(['permission' => $permission]);
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

        $permission = Permission::create([
            'app_id' => $app->id,
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
        ]);

        return response()->json(['permission' => $permission], 201);
    }
}
