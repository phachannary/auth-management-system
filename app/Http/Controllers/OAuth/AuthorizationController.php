<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthClient;
use App\Models\OAuthAuthCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AuthorizationController extends Controller
{
    /**
     * How long a pending OAuth request survives while the user logs in
     * (password, Google/Facebook, or OTP verification).
     */
    public const PENDING_REQUEST_TTL_MINUTES = 15;

    public function handleAuthorize(Request $request)
    {
        $clientId = $request->input('client_id');
        $redirectUri = $request->input('redirect_uri');

        // Until both client_id and redirect_uri are verified, never redirect to
        // redirect_uri - show an error page instead (RFC 6749 §4.1.2.1).
        // Otherwise this endpoint can be used as an open redirect.
        if (!is_string($clientId) || $clientId === '') {
            return $this->errorPage('invalid_request', 'The client_id parameter is missing.');
        }

        // Find the OAuth client
        $client = OAuthClient::where('client_id', $clientId)
            ->where('active', true)
            ->first();

        if (!$client) {
            return $this->errorPage('invalid_client', 'Unknown or inactive client_id.');
        }

        // Validate redirect URI
        if (!is_string($redirectUri) || !$this->isValidRedirectUri($redirectUri, $client->redirect_uris ?? [])) {
            return $this->errorPage('invalid_request', 'The redirect_uri is missing or not registered for this client.');
        }

        // redirect_uri is now trusted, so remaining errors are returned to the client
        $validator = Validator::make($request->all(), [
            'response_type' => 'required|in:code',
            'scope' => 'nullable|string',
            'state' => 'nullable|string',
        ]);

        $state = is_string($request->input('state')) ? $request->input('state') : null;

        if ($validator->fails()) {
            $error = $validator->errors()->has('response_type') ? 'unsupported_response_type' : 'invalid_request';
            return $this->errorRedirect($redirectUri, $error, $validator->errors()->first(), $state);
        }

        $scope = $request->input('scope') ?: 'openid profile email';

        // Check if user is already logged in
        if (Auth::check()) {
            // User is logged in - generate authorization code
            return $this->generateAuthorizationCode($request, $client, Auth::user(), $redirectUri, $scope, $state);
        }

        // User not logged in - store OAuth request in session and show login page.
        // It expires so an abandoned OAuth login cannot hijack a later normal
        // login (see DiscardExpiredOAuthRequest middleware).
        session([
            'oauth_request' => [
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'scope' => $scope,
                'state' => $state,
                'expires_at' => now()->addMinutes(self::PENDING_REQUEST_TTL_MINUTES)->timestamp,
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

        return Redirect::to($this->appendQuery($redirectUri, $params));
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
        // Exact match only. Prefix/wildcard matching allows lookalike hosts
        // (e.g. https://ds1.example.com.evil.com) to pass validation.
        return $uri === $pattern;
    }

    /**
     * Error shown to the user when the client or redirect_uri cannot be trusted.
     */
    private function errorPage(string $error, string $description)
    {
        return response()->view('oauth.error', [
            'error' => $error,
            'error_description' => $description,
        ], 400);
    }

    /**
     * Error returned to the client. Only call after redirect_uri has been validated.
     */
    private function errorRedirect(string $redirectUri, string $error, string $description, ?string $state)
    {
        $params = [
            'error' => $error,
            'error_description' => $description,
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return Redirect::to($this->appendQuery($redirectUri, $params));
    }

    private function appendQuery(string $uri, array $params): string
    {
        $separator = str_contains($uri, '?') ? '&' : '?';

        return $uri . $separator . http_build_query($params);
    }
}
