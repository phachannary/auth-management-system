<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CognitoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthApiController extends Controller
{
    protected $cognitoService;

    public function __construct(CognitoService $cognitoService)
    {
        $this->cognitoService = $cognitoService;
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $appSlug = $request->attributes->get('app_slug');
        $appId = $request->attributes->get('app_id');

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'cognito_sub' => $user->cognito_sub,
        ];

        if ($appSlug) {
            $roles = $user->rolesForApp($appSlug);
            $data['app'] = [
                'slug' => $appSlug,
                'id' => $appId,
            ];
            $data['roles'] = $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ];
            });
        }

        return response()->json(['user' => $data]);
    }

    public function logout(Request $request)
    {
        $accessToken = $request->bearerToken();
        $this->cognitoService->globalSignOut($accessToken);

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
