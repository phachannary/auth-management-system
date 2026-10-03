<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthClient;
use App\Models\OAuthAuthCode;
use App\Models\OAuthAccessToken;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TokenController extends Controller
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function token(Request $request)
    {
        $grantType = $request->input('grant_type');

        if ($grantType === 'authorization_code') {
            return $this->handleAuthorizationCodeGrant($request);
        } elseif ($grantType === 'refresh_token') {
            return $this->handleRefreshTokenGrant($request);
        }

        return response()->json([
            'error' => 'unsupported_grant_type',
            'error_description' => 'The authorization grant type is not supported',
        ], 400);
    }

    private function handleAuthorizationCodeGrant(Request $request)
    {
        $request->validate([
            'grant_type' => 'required|in:authorization_code',
            'code' => 'required|string',
            'redirect_uri' => 'required|string',
            'client_id' => 'required|string',
            'client_secret' => 'nullable|string',
        ]);

        $code = $request->input('code');
        $redirectUri = trim($request->input('redirect_uri'));
        $clientId = trim($request->input('client_id'));
        $clientSecret = $request->input('client_secret') ? trim($request->input('client_secret')) : null;

        // Find the OAuth client
        $client = OAuthClient::where('client_id', $clientId)
            ->where('active', true)
            ->first();

        if (!$client) {
            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed',
            ], 401);
        }

        // Validate client secret if client is confidential
        if ($client->confidential) {
            if (!$clientSecret || !hash_equals($clientSecret, $client->client_secret)) {
                return response()->json([
                    'error' => 'invalid_client',
                    'error_description' => 'Client authentication failed',
                ], 401);
            }
        }

        // Find and validate authorization code
        $authCode = OAuthAuthCode::where('code', hash('sha256', $code))
            ->where('client_id', $clientId)
            ->where('redirect_uri', $redirectUri)
            ->where('expires_at', '>', now())
            ->first();

        if (!$authCode) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Invalid authorization code',
            ], 400);
        }

        // Get the user
        $user = User::find($authCode->user_id);
        if (!$user) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'User not found',
            ], 400);
        }

        // Delete the authorization code (one-time use)
        $authCode->delete();

        // Generate tokens
        $accessToken = $this->jwtService->generateAccessToken([
            'sub' => (string) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'client_id' => $clientId,
        ]);

        $refreshToken = $this->jwtService->generateRefreshToken([
            'sub' => (string) $user->id,
            'client_id' => $clientId,
        ]);

        $idToken = $this->jwtService->generateIdToken([
            'sub' => (string) $user->id,
            'email' => $user->email,
            'email_verified' => !is_null($user->email_verified_at),
            'name' => $user->name,
            'client_id' => $clientId,
        ]);

        // Store access token in database
        $accessTokenRecord = OAuthAccessToken::create([
            'user_id' => $user->id,
            'client_id' => $clientId,
            'token' => hash('sha256', $accessToken),
            'expires_at' => now()->addHour(),
            'scopes' => $authCode->scopes,
        ]);

        // Store refresh token in database
        OAuthRefreshToken::create([
            'user_id' => $user->id,
            'client_id' => $clientId,
            'token' => hash('sha256', $refreshToken),
            'access_token_id' => $accessTokenRecord->id,
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => $refreshToken,
            'id_token' => $idToken,
        ]);
    }

    private function handleRefreshTokenGrant(Request $request)
    {
        $request->validate([
            'grant_type' => 'required|in:refresh_token',
            'refresh_token' => 'required|string',
            'client_id' => 'required|string',
            'client_secret' => 'nullable|string',
        ]);

        $refreshToken = $request->input('refresh_token');
        $clientId = trim($request->input('client_id'));
        $clientSecret = $request->input('client_secret') ? trim($request->input('client_secret')) : null;

        // Find the OAuth client
        $client = OAuthClient::where('client_id', $clientId)
            ->where('active', true)
            ->first();

        if (!$client) {
            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed',
            ], 401);
        }

        // Validate client secret if client is confidential
        if ($client->confidential) {
            if (!$clientSecret || !hash_equals($clientSecret, $client->client_secret)) {
                return response()->json([
                    'error' => 'invalid_client',
                    'error_description' => 'Client authentication failed',
                ], 401);
            }
        }

        // Find and validate refresh token
        $refreshTokenRecord = OAuthRefreshToken::where('token', hash('sha256', $refreshToken))
            ->where('client_id', $clientId)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->with('accessToken')
            ->first();

        if (!$refreshTokenRecord) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Invalid refresh token',
            ], 400);
        }

        // Get the user
        $user = User::find($refreshTokenRecord->user_id);
        if (!$user) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'User not found',
            ], 400);
        }

        // Rotate atomically: revoking and re-issuing happen together, and the
        // conditional update ensures a refresh token can only be used once even
        // under concurrent requests.
        return DB::transaction(function () use ($refreshTokenRecord, $user, $clientId) {
            $claimed = OAuthRefreshToken::whereKey($refreshTokenRecord->id)
                ->where('revoked', false)
                ->update(['revoked' => true]);

            if ($claimed === 0) {
                return response()->json([
                    'error' => 'invalid_grant',
                    'error_description' => 'Invalid refresh token',
                ], 400);
            }

            OAuthAccessToken::whereKey($refreshTokenRecord->access_token_id)->update(['revoked' => true]);

            return $this->issueRefreshedTokens($refreshTokenRecord, $user, $clientId);
        });
    }

    private function issueRefreshedTokens(OAuthRefreshToken $refreshTokenRecord, User $user, string $clientId)
    {
        // Generate new tokens
        $newAccessToken = $this->jwtService->generateAccessToken([
            'sub' => (string) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'client_id' => $clientId,
        ]);

        $newRefreshToken = $this->jwtService->generateRefreshToken([
            'sub' => (string) $user->id,
            'client_id' => $clientId,
        ]);

        $newIdToken = $this->jwtService->generateIdToken([
            'sub' => (string) $user->id,
            'email' => $user->email,
            'email_verified' => !is_null($user->email_verified_at),
            'name' => $user->name,
            'client_id' => $clientId,
        ]);

        // Store new access token in database
        $newAccessTokenRecord = OAuthAccessToken::create([
            'user_id' => $user->id,
            'client_id' => $clientId,
            'token' => hash('sha256', $newAccessToken),
            'expires_at' => now()->addHour(),
            'scopes' => $refreshTokenRecord->accessToken->scopes,
        ]);

        // Store new refresh token in database
        OAuthRefreshToken::create([
            'user_id' => $user->id,
            'client_id' => $clientId,
            'token' => hash('sha256', $newRefreshToken),
            'access_token_id' => $newAccessTokenRecord->id,
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'access_token' => $newAccessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => $newRefreshToken,
            'id_token' => $newIdToken,
        ]);
    }

    public function revoke(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'token_type_hint' => 'nullable|in:access_token,refresh_token',
        ]);

        $token = $request->input('token');
        $tokenTypeHint = $request->input('token_type_hint');

        // Try to revoke as access token first
        $accessTokenRecord = OAuthAccessToken::where('token', hash('sha256', $token))
            ->where('revoked', false)
            ->first();

        if ($accessTokenRecord) {
            $accessTokenRecord->update(['revoked' => true]);
            
            // Also revoke associated refresh token
            OAuthRefreshToken::where('access_token_id', $accessTokenRecord->id)
                ->update(['revoked' => true]);
            
            return response()->json([], 200);
        }

        // If not found as access token or hint is refresh_token, try as refresh token
        if (!$accessTokenRecord || $tokenTypeHint === 'refresh_token') {
            $refreshTokenRecord = OAuthRefreshToken::where('token', hash('sha256', $token))
                ->where('revoked', false)
                ->first();

            if ($refreshTokenRecord) {
                $refreshTokenRecord->update(['revoked' => true]);
                
                // Also revoke associated access token
                OAuthAccessToken::whereKey($refreshTokenRecord->access_token_id)
                    ->update(['revoked' => true]);
                
                return response()->json([], 200);
            }
        }

        // Token not found or already revoked - return 200 per RFC 7009
        return response()->json([], 200);
    }
}
