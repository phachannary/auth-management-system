<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CognitoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    protected $cognitoService;

    public function __construct(CognitoService $cognitoService)
    {
        $this->cognitoService = $cognitoService;
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string|max:255|regex:/^[a-zA-Z0-9_]+$/',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
            'terms' => 'required|accepted',
        ]);

        // Create local user first
        $user = User::create([
            'name' => $credentials['username'],
            'email' => $credentials['email'],
            'password' => bcrypt($credentials['password']),
            'email_verified_at' => null,
        ]);

        \Log::info('Local user created during registration: ' . $user->email);

        // Create AWS Cognito user
        $result = $this->cognitoService->signUp($credentials['username'], $credentials['password'], $credentials['email']);

        \Log::info('Registration result: ' . json_encode($result));

        if ($result['success']) {
            // Set session data for verification (not flash data)
            Session::put('verification_username', $credentials['username']);
            Session::put('verification_email', $credentials['email']);
            Session::put('verification_expires_at', now()->addMinutes(30));

            return redirect()->route('auth.verify')->with(
                'success', 'Registration successful! Please check your email for verification code.'
            );
        } else {
            // Delete local user if Cognito creation failed
            $user->delete();
            return back()->withErrors(['username' => $result['error']]);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        // Use username for AWS Cognito (required for email alias configuration)
        \Log::info('Attempting login with username: ' . $credentials['username']);
        $result = $this->cognitoService->initiateAuth($credentials['username'], $credentials['password']);

        \Log::info('Login result: ' . json_encode($result));

        if ($result['success']) {
            // Store tokens in session
            Session::put('cognito_tokens', $result['data']);

            // Get user details - AccessToken is inside AuthenticationResult
            $accessToken = $result['data']['AuthenticationResult']['AccessToken'] ?? null;
            if ($accessToken) {
                $userResult = $this->cognitoService->getUser($accessToken);
                if ($userResult['success']) {
                    Session::put('user', $userResult['data']);
                }
            }

            return redirect()->route('dashboard')->with('success', 'Login successful!');
        } else {
            return back()->withErrors(['username' => $result['error']]);
        }
    }

    public function showVerificationForm()
    {
        if (!session('username')) {
            return redirect()->route('auth.register');
        }

        return view('auth.verify');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'code' => 'required|string|size:6',
        ]);

        \Log::info('Attempting verification for username: ' . $request->username . ' with code: ' . $request->code);

        $result = $this->cognitoService->confirmSignUp(
            $request->username,
            $request->code
        );

        \Log::info('Verification result: ' . json_encode($result));

        if ($result['success']) {
            \Log::info('Verification successful, redirecting to login');

            // Clear the username from session after successful verification
            Session::forget('username');

            return redirect()->route('auth.login')
                ->with('success', 'Account verified! You can now login.')
                ->with('verified_username', $request->username);
        } else {
            \Log::error('Verification failed: ' . $result['error']);

            // Provide more specific error messages
            $errorMessage = $result['error'];
            if (strpos($errorMessage, 'Invalid verification code') !== false) {
                $errorMessage = 'Invalid verification code. Please check your email and try again.';
            } elseif (strpos($errorMessage, 'CodeMismatchException') !== false) {
                $errorMessage = 'The verification code is incorrect or has expired.';
            } elseif (strpos($errorMessage, 'NotAuthorizedException') !== false) {
                $errorMessage = 'This account is already confirmed or the code has expired. Please try logging in.';
            } elseif (strpos($errorMessage, 'User does not exist') !== false) {
                $errorMessage = 'User not found. Please register first.';
            }

            return back()->withErrors(['code' => $errorMessage]);
        }
    }

    public function resendVerificationCode(Request $request)
    {
        $request->validate([
            'username' => 'required',
        ]);

        $result = $this->cognitoService->resendConfirmationCode($request->username);

        if ($result['success']) {
            return back()->with('success', 'Verification code sent! Check your email.');
        } else {
            return back()->withErrors(['username' => $result['error']]);
        }
    }

    public function redirectToCognito()
    {
        $url = $this->cognitoService->getHostedUIUrl();

        // Debug: Log the URL
        Log::info('Cognito URL: ' . $url);

        return redirect($url);
    }

    public function handleCognitoCallback(Request $request)
    {
        if ($request->has('code')) {
            $result = $this->cognitoService->exchangeCodeForTokens($request->code);

            if ($result['success']) {
                $tokens = $result['data'];

                // Store tokens in session
                Session::put('cognito_tokens', [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'id_token' => $tokens['id_token'],
                    'expires_in' => $tokens['expires_in'],
                ]);

                // Get user details
                $userResult = $this->cognitoService->getUser($tokens['access_token']);
                if ($userResult['success']) {
                    Session::put('user', $userResult['data']);
                }

                return redirect()->route('dashboard')->with('success', 'Login successful!');
            }
        }

        return redirect()->route('auth.login')->withErrors(['login' => 'Authentication failed']);
    }

    public function logout()
    {
        $tokens = Session::get('cognito_tokens');

        if ($tokens && isset($tokens['access_token'])) {
            $this->cognitoService->globalSignOut($tokens['access_token']);
        }

        Session::forget(['cognito_tokens', 'user']);

        return redirect()->route('auth.login')->with('success', 'You have been logged out.');
    }

    public function dashboard()
    {
        Log::info('Dashboard access attempt', [
            'auth_check' => Auth::check(),
            'auth_id' => Auth::id(),
            'session_cognito_tokens' => Session::has('cognito_tokens'),
            'session_user' => Session::get('user'),
        ]);

        // Check if user is logged in via Laravel Auth (Google login) or Cognito
        if (!Auth::check() && !Session::has('cognito_tokens')) {
            Log::error('Dashboard access denied: Not authenticated');
            return redirect()->route('auth.login');
        }

        // Get user from Laravel Auth (Google login) or session (Cognito)
        if (Auth::check()) {
            $user = Auth::user();
            Log::info('Dashboard accessed via Laravel Auth', ['user_email' => $user->email]);
        } else {
            $user = Session::get('user');
            Log::info('Dashboard accessed via session', ['user_email' => $user->email ?? null]);
        }

        return view('dashboard', compact('user'));
    }

    public function refreshTokens()
    {
        $tokens = Session::get('cognito_tokens');

        if ($tokens && isset($tokens['refresh_token'])) {
            $result = $this->cognitoService->refreshToken($tokens['refresh_token']);

            if ($result['success']) {
                $authResult = $result['data']['AuthenticationResult'];

                // Update tokens in session
                Session::put('cognito_tokens', [
                    'access_token' => $authResult['AccessToken'],
                    'refresh_token' => $tokens['refresh_token'], // Keep the same refresh token
                    'id_token' => $authResult['IdToken'],
                    'expires_in' => $authResult['ExpiresIn'],
                ]);

                return response()->json(['success' => true, 'message' => 'Tokens refreshed']);
            }
        }

        return response()->json(['success' => false, 'message' => 'Token refresh failed']);
    }
}
