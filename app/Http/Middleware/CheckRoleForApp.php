<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckRoleForApp
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();
        $appSlug = $request->attributes->get('app_slug');

        if (!$appSlug) {
            Log::warning('CheckRoleForApp: No app_slug in request attributes');
            return response()->json(['error' => 'Application context not found.'], 500);
        }

        if (!$user->hasRoleInApp($role, $appSlug)) {
            Log::warning('Role check failed', [
                'user_id' => $user->id,
                'required_role' => $role,
                'app_slug' => $appSlug,
            ]);
            return response()->json([
                'error' => 'Unauthorized',
                'message' => "Role '{$role}' is required for this action in application '{$appSlug}'."
            ], 403);
        }

        return $next($request);
    }
}
