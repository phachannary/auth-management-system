<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckPermissionForApp
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        $appSlug = $request->attributes->get('app_slug');

        if (!$appSlug) {
            Log::warning('CheckPermissionForApp: No app_slug in request attributes');
            return response()->json(['error' => 'Application context not found.'], 500);
        }

        // Get user's roles for this app
        $roles = $user->rolesForApp($appSlug);

        // Check if any of these roles have the required permission
        $hasPermission = Permission::where('slug', $permission)
            ->whereHas('app', function ($query) use ($appSlug) {
                $query->where('slug', $appSlug);
            })
            ->whereHas('roles', function ($query) use ($roles) {
                $query->whereIn('id', $roles->pluck('id'));
            })
            ->exists();

        if (!$hasPermission) {
            Log::warning('Permission check failed', [
                'user_id' => $user->id,
                'required_permission' => $permission,
                'app_slug' => $appSlug,
            ]);
            return response()->json([
                'error' => 'Unauthorized',
                'message' => "Permission '{$permission}' is required for this action in application '{$appSlug}'."
            ], 403);
        }

        return $next($request);
    }
}
