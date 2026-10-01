<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthClient;
use App\Models\OAuthAuthCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AuthorizationController extends Controller
{
    public function handleAuthorize(Request $request)
    {
        $request->validate([
            'client_id' => 'required|string',
            'redirect_uri' => 'required|string',
            'response_type' => 'required|in:code',
            'scope' => 'nullable|string',
            'state' => 'nullable|string',
        ]);

        $clientId = $request->input('client_id');
        $redirectUri = $request->input('redirect_uri');
        $scope = $request->input('scope', 'openid profile email');
        $state = $request->input('state');

        // Find the OAuth client
        $client = OAuthClient::where('client_id', $clientId)
            ->where('active', true)
            ->first();

        if (!$client) {
            return $this->errorRedirect($redirectUri, 'invalid_client', 'Invalid client_id', $state);
        }

        // Validate redirect URI
        $allowedUris = $client->redirect_uris;
        if (!$this->isValidRedirectUri($redirectUri, $allowedUris)) {
            return $this->errorRedirect($redirectUri, 'invalid_redirect_uri', 'Invalid redirect_uri', $state);
        }

        // Check if user is already logged in
        if (Auth::check()) {
            // User is logged in - generate authorization code
            return $this->generateAuthorizationCode($request, $client, Auth::user(), $redirectUri, $scope, $state);
        }

        // User not logged in - store OAuth request in session and show login page
        session([
            'oauth_request' => [
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'scope' => $scope,
                'state' => $state,
            ]
        ]);

        return redirect()->route('oauth.login');
    }

    private function generateAuthorizationCode(Request $request, OAuthClient $client, User $user, string $redirectUri, string $scope, ?string $state)
    {
        // Generate authorization code
        $code = Str::random(64);
        
        // Store authorization code in database
        OAuthAuthCode::create([
            'user_id' => $user->id,
            'client_id' => $client->client_id,
            'code' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10),
            'redirect_uri' => $redirectUri,
            'scopes' => explode(' ', $scope),
            'state' => $state,
        ]);

        // Clear OAuth request from session
        session()->forget('oauth_request');

        // Redirect to client with authorization code
        $params = [
            'code' => $code,
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return Redirect::to($redirectUri . '?' . http_build_query($params));
    }

    private function isValidRedirectUri(string $uri, array $allowedUris): bool
    {
        foreach ($allowedUris as $allowed) {
            if ($this->urisMatch($uri, $allowed)) {
                return true;
            }
        }
        return false;
    }

    private function urisMatch(string $uri, string $pattern): bool
    {
        // Exact match
        if ($uri === $pattern) {
            return true;
        }

        // Wildcard match (e.g., https://example.com/*)
        if (str_ends_with($pattern, '*')) {
            $prefix = substr($pattern, 0, -1);
            return str_starts_with($uri, $prefix);
        }

        return false;
    }

    private function errorRedirect(string $redirectUri, string $error, string $description, ?string $state)
    {
        $params = [
            'error' => $error,
            'error_description' => $description,
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return Redirect::to($redirectUri . '?' . http_build_query($params));
    }
}
