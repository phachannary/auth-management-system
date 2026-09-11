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

        if (!$result['success']) {
            return back()->withErrors(['username' => $result['error']]);
        }

        // Check if verification email was sent
        $codeDeliveryDetails = $result['data']['CodeDeliveryDetails'] ?? null;
        $userConfirmed = $result['data']['UserConfirmed'] ?? false;

        \Log::info('Registration completed', [
            'username' => $credentials['username'],
            'email' => $credentials['email'],
            'user_confirmed' => $userConfirmed,
            'code_delivery_details' => $codeDeliveryDetails
        ]);

        // Store code delivery details in session for user feedback
        if ($codeDeliveryDetails) {
            Session::put('code_delivery_details', $codeDeliveryDetails);
        }

        // Create local user (cognito_sub will be added after confirmation)
        // Store username in 'name' field for consistent username-based login lookup
        $user = User::create([
            'name' => $credentials['username'],
            'email' => $credentials['email'],
            'password' => bcrypt($credentials['password']),
            'email_verified_at' => null,
        ]);



        // Set session data for verification (not flash data)
        Session::put('verification_username', $credentials['username']);
        Session::put('verification_email', $credentials['email']);
        Session::put('verification_expires_at', now()->addMinutes(30));

        // Provide detailed feedback about verification email
        $message = 'Registration successful! ';
        if ($codeDeliveryDetails) {
            $destination = $codeDeliveryDetails['Destination'] ?? 'your email';
            $message .= "A verification code has been sent to $destination. ";
        } else {
            $message .= 'Please check your email for verification code. ';
        }
        $message .= 'If you don\'t receive it within a few minutes, use the resend option.';

        return redirect()->route('auth.verify')->with('success', $message);
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
        $result = $this->cognitoService->initiateAuth($credentials['username'], $credentials['password']);

        if ($result['success']) {
            // Regenerate session to prevent session fixation
            Session::regenerate();

            // Get the raw Cognito response
            $cognitoResponse = $result['data']['AuthenticationResult'];
            
            // Store tokens in session with flattened structure for consistency
            Session::put('cognito_tokens', [
                'access_token' => $cognitoResponse['AccessToken'],
                'refresh_token' => $cognitoResponse['RefreshToken'],
                'id_token' => $cognitoResponse['IdToken'],
                'expires_in' => $cognitoResponse['ExpiresIn'],
                'token_type' => $cognitoResponse['TokenType'] ?? 'Bearer',
                'token_received_at' => now()->timestamp,
            ]);

            // Get user details and update with cognito_sub
            $accessToken = $cognitoResponse['AccessToken'] ?? null;
            $idToken = $cognitoResponse['IdToken'] ?? null;

            if ($accessToken) {
                $userResult = $this->cognitoService->getUser($accessToken);
                if ($userResult['success']) {
                    Session::put('user', $userResult['data']);

                    // Get Cognito username from the response
                    $cognitoUsername = $userResult['data']['Username'] ?? null;
                    
                    // Look up local user using the login username (stored in 'name' field)
                    // This ensures consistent username-based authentication
                    $localUser = User::where('name', $credentials['username'])->first();

                    if (!$localUser) {
                        return back()->withErrors(['username' => 'User account not found. Please contact support.']);
                    }

                    // Update local user with Cognito identity information
                    if ($cognitoUsername) {
                        $localUser->cognito_username = $cognitoUsername;
                    }

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

                    // Log in with Laravel Auth
                    Auth::login($localUser);
                } else {
                    return back()->withErrors(['username' => 'Authentication failed. Please try again.']);
                }
            } else {
                return back()->withErrors(['username' => 'Authentication failed. Please try again.']);
            }

            return redirect()->route('dashboard')->with('success', 'Login successful!');
        } else {
            // Handle UserNotConfirmedException specifically
            if (isset($result['error']) && $result['error'] === 'UserNotConfirmedException') {
                // Store username in session for verification page
                Session::put('verification_username', $result['username'] ?? $credentials['username']);
                
                // Redirect to verification page with a helpful message
                return redirect()->route('auth.verify')
                    ->with('error', 'Please verify your email before logging in.')
                    ->with('resend_available', true);
            }
            
            return back()->withErrors(['username' => $result['error']]);
        }
    }

    public function showVerificationForm()
    {
        // Allow username from session or from old input (after failed verification)
        $username = session('username') ?: session('verification_username') ?: old('username');
        
        // Don't redirect if no username - allow manual entry for unconfirmed users
        // who are redirected from login

        // Check if user is already confirmed in Cognito (only if we have a username)
        if ($username) {
            $statusResult = $this->cognitoService->getUserStatus($username);
            if ($statusResult['success'] && $statusResult['status'] === 'CONFIRMED') {
                // User is already confirmed, clear ALL verification-related session data
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
                return redirect()->route('auth.login')
                    ->with('success', 'Your account is already verified. Please login.')
                    ->with('verified_username', $username);
            }
        }

        return view('auth.verify', [
            'username' => $username,
            'resend_available' => session('resend_available')
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'code' => 'required|string|size:6',
        ]);

        // Store username in session for future use
        Session::put('verification_username', $request->username);



        // Check if user is already confirmed in Cognito before attempting verification
        $statusResult = $this->cognitoService->getUserStatus($request->username);
        if ($statusResult['success'] && $statusResult['status'] === 'CONFIRMED') {

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

            // Regular registration flow - just redirect to login
            Session::forget(['username', 'verification_username', 'verification_email', 'verification_expires_at']);
            return redirect()->route('auth.login')->with('success', 'Your account is already verified. Please login.');
        }

        $result = $this->cognitoService->confirmSignUp(
            $request->username,
            $request->code
        );



        if ($result['success']) {


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

            // Regular signup flow - update local user and redirect to login
            $localUser = User::where('name', $request->username)->first();
            if ($localUser) {
                $localUser->email_verified_at = now();
                $localUser->save();
            }

            Session::forget(['username', 'verification_username']);

            return redirect()->route('auth.login')
                ->with('success', 'Account verified! You can now login.')
                ->with('verified_username', $request->username);
        } else {


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

            return back()->withInput(['username' => $request->username])->withErrors(['code' => $errorMessage]);
        }
    }

    public function resendVerificationCode(Request $request)
    {
        $request->validate([
            'username' => 'required',
        ]);

        $result = $this->cognitoService->resendConfirmationCode($request->username);

        if ($result['success']) {
            $codeDeliveryDetails = $result['data']['CodeDeliveryDetails'] ?? null;
            $message = 'Verification code resent! ';

            if ($codeDeliveryDetails) {
                $destination = $codeDeliveryDetails['Destination'] ?? 'your email';
                $message .= "Check $destination for the new code.";
                Session::put('code_delivery_details', $codeDeliveryDetails);
            } else {
                $message .= 'Please check your email.';
            }

            \Log::info('Verification code resent', [
                'username' => $request->username,
                'code_delivery_details' => $codeDeliveryDetails
            ]);

            return back()->with('success', $message);
        } else {
            \Log::error('Failed to resend verification code', [
                'username' => $request->username,
                'error' => $result['error']
            ]);
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
            // Get username from authenticated user for SECRET_HASH calculation
            $username = Auth::check() ? Auth::user()->name : null;
            
            $result = $this->cognitoService->refreshToken($tokens['refresh_token'], $username);

            if ($result['success']) {
                $authResult = $result['data']['AuthenticationResult'];

                // Update tokens in session with flattened structure
                // (Cognito may return a new refresh token if token rotation is enabled)
                Session::put('cognito_tokens', [
                    'access_token' => $authResult['AccessToken'],
                    'refresh_token' => $authResult['RefreshToken'] ?? $tokens['refresh_token'],
                    'id_token' => $authResult['IdToken'],
                    'expires_in' => $authResult['ExpiresIn'],
                    'token_type' => $authResult['TokenType'] ?? 'Bearer',
                    'token_received_at' => now()->timestamp,
                ]);

                return response()->json(['success' => true, 'message' => 'Tokens refreshed']);
            }
        }

        return response()->json(['success' => false, 'message' => 'Token refresh failed']);
    }

    public function logout()
    {
        $tokens = Session::get('cognito_tokens');

        // Revoke tokens in Cognito if access token is available
        if ($tokens && isset($tokens['access_token'])) {
            $this->cognitoService->globalSignOut($tokens['access_token']);
        }

        // Clear local session
        Session::forget(['cognito_tokens', 'user']);
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();

        return redirect()->route('auth.login')->with('success', 'You have been logged out.');
    }
}
