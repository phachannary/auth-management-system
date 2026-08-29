<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\CognitoAppClient;
use App\Services\CognitoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyCognitoToken
{
    protected $cognitoService;

    public function __construct(CognitoService $cognitoService)
    {
        $this->cognitoService = $cognitoService;
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'No token provided.'], 401);
        }

        // Validate the Cognito JWT
        $result = $this->cognitoService->validateAccessToken($token);

        if (!$result['success']) {
            Log::warning('Cognito token validation failed', ['error' => $result['error']]);
            return response()->json(['error' => 'Invalid or expired token.'], 401);
        }

        $payload = $result['data'];
        $sub = $payload['sub'] ?? null;
        $clientId = $payload['client_id'] ?? null;
        $email = $payload['email'] ?? null;

        if (!$sub) {
            return response()->json(['error' => 'Token missing subject (sub).'], 401);
        }

        if (!$clientId) {
            return response()->json(['error' => 'Token missing client_id.'], 401);
        }

        // Get the requested app slug from header
        $appSlug = $request->header('X-App-Slug');

        if (!$appSlug) {
            return response()->json(['error' => 'X-App-Slug header is required.'], 400);
        }

        // Verify that the Cognito client_id belongs to the requested app
        $cognitoClient = CognitoAppClient::findByClientIdAndAppSlug($clientId, $appSlug);

        if (!$cognitoClient) {
            Log::warning('App client validation failed', [
                'client_id' => $clientId,
                'app_slug' => $appSlug,
            ]);
            return response()->json([
                'error' => 'Invalid app client for requested application.',
                'message' => 'The token was not issued for this application.'
            ], 403);
        }

        // Find user by cognito_sub first, fallback to email for backward compatibility
        $user = User::where('cognito_sub', $sub)->first();

        if (!$user && $email) {
            $user = User::where('email', $email)->first();
            // Update user with cognito_sub if found by email
            if ($user) {
                $user->cognito_sub = $sub;
                $user->cognito_username = $payload['cognito:username'] ?? null;
                $user->save();
                Log::info('Updated user with cognito_sub', ['user_id' => $user->id, 'sub' => $sub]);
            }
        }

        if (!$user) {
            return response()->json(['error' => 'User not found. Please login via the web app first.'], 404);
        }

        // Store app context in request for later use
        $request->attributes->set('app_id', $cognitoClient->app_id);
        $request->attributes->set('app_slug', $appSlug);
        $request->attributes->set('cognito_client_id', $clientId);
        $request->attributes->set('cognito_sub', $sub);

        $request->setUserResolver(fn() => $user);

        return $next($request);
    }
}
