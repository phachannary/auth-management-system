<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyOAuthToken
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function handle(Request $request, Closure $next)
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

        // Store user info in request for controllers to use
        $request->attributes->set('oauth_user_id', $payload['sub']);
        $request->attributes->set('oauth_email', $payload['email'] ?? null);
        $request->attributes->set('oauth_name', $payload['name'] ?? null);
        $request->attributes->set('oauth_client_id', $payload['client_id'] ?? null);

        return $next($request);
    }
}
