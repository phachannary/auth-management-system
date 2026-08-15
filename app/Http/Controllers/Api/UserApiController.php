<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class UserApiController extends Controller
{
    public function index(Request $request)
    {
        $users = User::select('id', 'name', 'email', 'email_verified_at', 'created_at')
            ->paginate(20);

        return response()->json($users);
    }

    public function show($id)
    {
        $user = User::select('id', 'name', 'email', 'email_verified_at', 'created_at')
            ->findOrFail($id);

        return response()->json(['user' => $user]);
    }

    public function assignRole(Request $request, $userId)
    {
        $request->validate([
            'app_slug' => 'required|string',
            'role_slug' => 'required|string',
        ]);

        $user = User::findOrFail($userId);
        $app = App::where('slug', $request->app_slug)->firstOrFail();
        $role = Role::where('slug', $request->role_slug)
                    ->where('app_id', $app->id)
                    ->firstOrFail();

        \DB::table('user_app_role')->updateOrInsert(
            ['user_id' => $user->id, 'app_id' => $app->id, 'role_id' => $role->id],
            ['created_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['message' => "Role '{$role->name}' assigned to user in app '{$app->name}'."]);
    }

    public function removeRole(Request $request, $userId)
    {
        $request->validate([
            'app_slug' => 'required|string',
            'role_slug' => 'required|string',
        ]);

        $user = User::findOrFail($userId);
        $app = App::where('slug', $request->app_slug)->firstOrFail();
        $role = Role::where('slug', $request->role_slug)
                    ->where('app_id', $app->id)
                    ->firstOrFail();

        \DB::table('user_app_role')
            ->where('user_id', $user->id)
            ->where('app_id', $app->id)
            ->where('role_id', $role->id)
            ->delete();

        return response()->json(['message' => "Role removed successfully."]);
    }
}
