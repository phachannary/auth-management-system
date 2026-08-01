<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CognitoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Models\User;

class CognitoLoginController extends Controller
{
    protected $cognitoService;

    public function __construct(CognitoService $cognitoService)
    {
        $this->cognitoService = $cognitoService;
    }

    /**
     * Redirect to Cognito Hosted UI (handles Google, Facebook, and email/password)
     */
    public function redirectToCognito()
    {
        $url = $this->cognitoService->getHostedUIUrl(route('auth.cognito.callback'));

        Log::info('Redirecting to Cognito Hosted UI', ['url' => $url]);

        return redirect($url);
    }

    /**
     * Redirect directly to Google login via Cognito
     */
    public function redirectToGoogle()
    {
        $url = $this->cognitoService->getHostedUIUrl(route('auth.cognito.callback'), 'Google');

        return redirect($url);
    }

    /**
     * Redirect directly to Facebook login via Cognito
     */
    public function redirectToFacebook()
    {
        $url = $this->cognitoService->getHostedUIUrl(route('auth.cognito.callback'), 'Facebook');

        return redirect($url);
    }

    /**
     * Handle callback from Cognito Hosted UI
     */
    public function handleCognitoCallback(Request $request)
    {
        try {
            if ($request->has('error')) {
                Log::error('Cognito callback error', [
                    'error' => $request->get('error'),
                    'description' => $request->get('error_description'),
                ]);
                return redirect()->route('auth.login')
                    ->with('error', $request->get('error_description', 'Authentication failed'));
            }

            $code = $request->get('code');

            if (!$code) {
                return redirect()->route('auth.login')->with('error', 'No authorization code received');
            }

            // Exchange code for tokens
            $tokenResult = $this->cognitoService->exchangeCodeForTokens(
                $code,
                route('auth.cognito.callback')
            );

            if (!$tokenResult['success']) {
                Log::error('Token exchange failed', ['error' => $tokenResult['error']]);
                return redirect()->route('auth.login')->with('error', 'Token exchange failed');
            }

            $tokens = $tokenResult['data'];
            $idToken = $tokens['id_token'] ?? null;
            $accessToken = $tokens['access_token'] ?? null;
            $refreshToken = $tokens['refresh_token'] ?? null;

            if (!$idToken || !$accessToken) {
                return redirect()->route('auth.login')->with('error', 'Invalid tokens received');
            }

            // Validate ID token using JWKS
            $validationResult = $this->cognitoService->validateIdToken($idToken);

            if (!$validationResult['success']) {
                Log::error('ID token validation failed', ['error' => $validationResult['error']]);
                return redirect()->route('auth.login')->with('error', 'Token validation failed');
            }

            $claims = $validationResult['data'];
            $email = $claims['email'] ?? null;
            $name = $claims['name'] ?? $claims['cognito:username'] ?? null;

            if (!$email) {
                return redirect()->route('auth.login')->with('error', 'No email found in token');
            }

            // Create or update local user
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name ?? explode('@', $email)[0],
                    'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                    'email_verified_at' => now(),
                ]
            );

            // Update name if changed
            if ($name && $user->name !== $name) {
                $user->name = $name;
                $user->save();
            }

            // Store Cognito tokens in session
            Session::put('cognito_tokens', [
                'access_token' => $accessToken,
                'id_token' => $idToken,
                'refresh_token' => $refreshToken,
                'expires_in' => $tokens['expires_in'] ?? 3600,
                'token_received_at' => now()->timestamp,
            ]);

            // Log in with Laravel Auth
            Auth::login($user, true);

            Log::info('User logged in via Cognito Hosted UI', ['email' => $email]);

            return redirect()->route('dashboard')->with('success', 'Login successful!');

        } catch (\Exception $e) {
            Log::error('Cognito callback exception: ' . $e->getMessage());
            return redirect()->route('auth.login')->with('error', 'Login failed. Please try again.');
        }
    }

    /**
     * Logout - clear local session and redirect to Cognito logout
     */
    public function logout()
    {
        $tokens = Session::get('cognito_tokens');

        // Revoke tokens in Cognito
        if ($tokens && isset($tokens['access_token'])) {
            $this->cognitoService->globalSignOut($tokens['access_token']);
        }

        // Clear local session
        Session::forget(['cognito_tokens']);
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();

        // Redirect to Cognito logout endpoint to clear SSO session
        $logoutUrl = $this->cognitoService->getLogoutUrl(route('auth.login'));

        return redirect($logoutUrl);
    }
}
