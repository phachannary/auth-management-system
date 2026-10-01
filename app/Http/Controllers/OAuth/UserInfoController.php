<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthAccessToken;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserInfoController extends Controller
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function userinfo(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'Access token is required',
            ], 401);
        }

        // Validate the JWT token
        $validation = $this->jwtService->validateToken($token);

        if (!$validation['success']) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'Invalid or expired access token',
            ], 401);
        }

        $payload = $validation['data'];

        // Check if it's an access token
        if (!isset($payload['type']) || $payload['type'] !== 'access_token') {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'Token must be an access token',
            ], 401);
        }

        // Verify token exists in database and is not revoked
        $accessTokenRecord = OAuthAccessToken::where('token', hash('sha256', $token))
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$accessTokenRecord) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'Token not found or revoked',
            ], 401);
        }

        // Get the user
        $user = User::find($payload['sub']);

        if (!$user) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'User not found',
            ], 404);
        }

        // Return user info (OIDC standard claims)
        return response()->json([
            'sub' => (string) $user->id,
            'email' => $user->email,
            'email_verified' => !is_null($user->email_verified_at),
            'name' => $user->name,
            'given_name' => explode(' ', $user->name)[0] ?? '',
            'family_name' => explode(' ', $user->name)[1] ?? '',
        ]);
    }
}
