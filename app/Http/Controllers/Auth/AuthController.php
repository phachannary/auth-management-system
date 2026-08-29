<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CognitoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Models\User;

class AuthController extends Controller
{
    protected $cognitoService;

    public function __construct(CognitoService $cognitoService)
    {
        $this->cognitoService = $cognitoService;
    }

    public function showLoginForm()
    {
        // Clear stale verification session data when visiting login page
        Session::forget([
            'username',
            'verification_username',
            'verification_email',
            'verification_expires_at',
            'verification_otp_session_id',
            'verification_code_sent_at',
            'verification_already_confirmed',
            'google_oauth_email',
            'google_oauth_name',
            'google_oauth_id',
            'facebook_oauth_email',
            'facebook_oauth_name',
            'facebook_oauth_id',
            'auth_state',
        ]);

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

        // Create AWS Cognito user first to get the sub
        $result = $this->cognitoService->signUp($credentials['username'], $credentials['password'], $credentials['email']);

        \Log::info('Registration result: ' . json_encode($result));

        if (!$result['success']) {
            return back()->withErrors(['username' => $result['error']]);
        }

        // Create local user (cognito_sub will be added after confirmation)
        $user = User::create([
            'name' => $credentials['username'],
            'email' => $credentials['email'],
            'password' => bcrypt($credentials['password']),
            'email_verified_at' => null,
        ]);

        \Log::info('Local user created during registration: ' . $user->email);

        // Set session data for verification (not flash data)
        Session::put('verification_username', $credentials['username']);
        Session::put('verification_email', $credentials['email']);
        Session::put('verification_expires_at', now()->addMinutes(30));

        return redirect()->route('auth.verify')->with(
            'success', 'Registration successful! Please check your email for verification code.'
        );
    }

    public function login(Request $request)
    {
        // Clear stale verification session data on login attempt
        Session::forget([
            'username',
            'verification_username',
            'verification_email',
            'verification_expires_at',
            'verification_otp_session_id',
            'verification_code_sent_at',
            'verification_already_confirmed',
            'google_oauth_email',
            'google_oauth_name',
            'google_oauth_id',
            'facebook_oauth_email',
            'facebook_oauth_name',
            'facebook_oauth_id',
            'auth_state',
        ]);

        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        // Use username for AWS Cognito (required for email alias configuration)
        \Log::info('Attempting login with username: ' . $credentials['username']);
        $result = $this->cognitoService->initiateAuth($credentials['username'], $credentials['password']);

        \Log::info('Login result: ' . json_encode($result));

        if ($result['success']) {
            // Regenerate session to prevent session fixation
            Session::regenerate();

            // Store tokens in session
            Session::put('cognito_tokens', $result['data']);

            // Get user details and update with cognito_sub
            $accessToken = $result['data']['AuthenticationResult']['AccessToken'] ?? null;
            $idToken = $result['data']['AuthenticationResult']['IdToken'] ?? null;

            if ($accessToken) {
                $userResult = $this->cognitoService->getUser($accessToken);
                if ($userResult['success']) {
                    Session::put('user', $userResult['data']);

                    // Update local user with Cognito sub and username
                    $cognitoUsername = $userResult['data']['Username'] ?? null;
                    $localUser = User::where('email', $credentials['username'])->first();

                    if ($localUser && $cognitoUsername) {
                        $localUser->cognito_username = $cognitoUsername;

                        // Extract sub from ID token if available
                        if ($idToken) {
                            $tokenValidation = $this->cognitoService->validateIdToken($idToken);
                            if ($tokenValidation['success']) {
                                $sub = $tokenValidation['data']['sub'] ?? null;
                                if ($sub) {
                                    $localUser->cognito_sub = $sub;
                                }
                            }
                        }

                        $localUser->save();
                    }

                    // Log in with Laravel Auth
                    Auth::login($localUser);
                }
            }

            return redirect()->route('dashboard')->with('success', 'Login successful!');
        } else {
            return back()->withErrors(['username' => $result['error']]);
        }
    }

    public function showVerificationForm()
    {
        if (!session('username') && !session('verification_username')) {
            return redirect()->route('auth.register');
        }

        $username = session('username') ?: session('verification_username');

        // Check if user is already confirmed in Cognito
        $statusResult = $this->cognitoService->getUserStatus($username);
        if ($statusResult['success'] && $statusResult['status'] === 'CONFIRMED') {
            // User is already confirmed, clear session and redirect to login
            Session::forget(['username', 'verification_username', 'verification_email', 'verification_expires_at']);
            return redirect()->route('auth.login')
                ->with('success', 'Your account is already verified. Please login.')
                ->with('verified_username', $username);
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
            \Log::info('Verification successful for: ' . $request->username);

            // Check if this is a Google OAuth flow
            $googleEmail = session('google_oauth_email');
            if ($googleEmail) {
                $googleName = session('google_oauth_name');
                $googleId = session('google_oauth_id');

                $user = User::where('email', $googleEmail)->first();
                if (!$user) {
                    $user = User::create([
                        'name' => $googleName ?? explode('@', $googleEmail)[0],
                        'email' => $googleEmail,
                        'google_id' => $googleId,
                        'email_verified_at' => now(),
                        'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                    ]);
                } else {
                    if (!$user->google_id) {
                        $user->google_id = $googleId;
                        $user->email_verified_at = now();
                        $user->save();
                    }
                }

                Auth::login($user);
                Session::forget(['username', 'verification_username', 'google_oauth_name', 'google_oauth_email', 'google_oauth_id']);

                return redirect()->route('dashboard')->with('success', 'Email verified! Welcome.');
            }

            // Check if this is a Facebook OAuth flow
            $facebookEmail = session('facebook_oauth_email');
            if ($facebookEmail) {
                $facebookName = session('facebook_oauth_name');
                $facebookId = session('facebook_oauth_id');

                $user = User::where('email', $facebookEmail)->first();
                if (!$user) {
                    $user = User::create([
                        'name' => $facebookName ?? explode('@', $facebookEmail)[0],
                        'email' => $facebookEmail,
                        'facebook_id' => $facebookId,
                        'email_verified_at' => now(),
                        'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                    ]);
                } else {
                    if (!$user->facebook_id) {
                        $user->facebook_id = $facebookId;
                        $user->email_verified_at = now();
                        $user->save();
                    }
                }

                Auth::login($user);
                Session::forget(['username', 'verification_username', 'facebook_oauth_name', 'facebook_oauth_email', 'facebook_oauth_id']);

                return redirect()->route('dashboard')->with('success', 'Email verified! Welcome.');
            }

            // Regular signup flow - redirect to login
            Session::forget(['username', 'verification_username']);

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

    public function dashboard()
    {
        if (!Auth::check()) {
            return redirect()->route('auth.login');
        }

        $user = Auth::user();
        $cognitoTokens = Session::get('cognito_tokens');

        return view('dashboard', compact('user', 'cognitoTokens'));
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
                    'refresh_token' => $tokens['refresh_token'],
                    'id_token' => $authResult['IdToken'],
                    'expires_in' => $authResult['ExpiresIn'],
                    'token_received_at' => now()->timestamp,
                ]);

                return response()->json(['success' => true, 'message' => 'Tokens refreshed']);
            }
        }

        return response()->json(['success' => false, 'message' => 'Token refresh failed']);
    }
}
