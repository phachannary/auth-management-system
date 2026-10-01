<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class OAuthLoginController extends Controller
{
    public function showLoginForm()
    {
        $oauthRequest = session('oauth_request');

        if (!$oauthRequest) {
            return redirect()->route('auth.login')
                ->with('error', 'Invalid OAuth request. Please try again.');
        }

        return view('oauth.login', [
            'client_id' => $oauthRequest['client_id'],
            'redirect_uri' => $oauthRequest['redirect_uri'],
        ]);
    }

    public function login(Request $request)
    {
        $oauthRequest = session('oauth_request');

        if (!$oauthRequest) {
            return redirect()->route('auth.login')
                ->with('error', 'Invalid OAuth request. Please try again.');
        }

        // Use the existing AuthController login logic
        // We'll redirect to the regular login form with OAuth context
        return redirect()->route('auth.login')
            ->with('oauth_flow', true);
    }

    public function handleSuccessfulAuth()
    {
        $oauthRequest = session('oauth_request');

        if (!$oauthRequest) {
            return redirect()->route('dashboard');
        }

        // User is now logged in, redirect back to authorize endpoint
        return redirect()->route('oauth.authorize', [
            'client_id' => $oauthRequest['client_id'],
            'redirect_uri' => $oauthRequest['redirect_uri'],
            'response_type' => 'code',
            'scope' => $oauthRequest['scope'] ?? 'openid profile email',
            'state' => $oauthRequest['state'] ?? null,
        ]);
    }
}
