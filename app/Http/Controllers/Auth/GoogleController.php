<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\CognitoService;

class GoogleController extends Controller
{
    protected $cognitoService;

    public function __construct(CognitoService $cognitoService)
    {
        $this->cognitoService = $cognitoService;
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            if (Auth::check()) {
                // If already logged in and this is an OAuth flow, redirect to authorize
                if (session('oauth_request')) {
                    return redirect()->route('oauth.authorize', [
                        'client_id' => session('oauth_request')['client_id'],
                        'redirect_uri' => session('oauth_request')['redirect_uri'],
                        'response_type' => 'code',
                        'scope' => session('oauth_request')['scope'] ?? 'openid profile email',
                        'state' => session('oauth_request')['state'] ?? null,
                    ]);
                }
                return redirect()->route('dashboard');
            }

            session(['auth_state' => 'oauth_pending']);

            $googleUser = Socialite::driver('google')->user();

            $email = $googleUser->getEmail();
            $name = $googleUser->getName();
            $googleId = $googleUser->getId();

            if (!$email) {
                session()->forget(['auth_state']);
                return redirect()->route('auth.login')->with('error', 'Google email not found.');
            }

            // Check if user already exists in local database with Google ID
            $existingUser = User::where('google_id', $googleId)->first();

            if ($existingUser && $existingUser->email_verified_at) {
                // User exists and is verified - log them in directly
                Auth::login($existingUser);
                Session::regenerate();
                session()->forget(['auth_state']);

                // Revoke all existing OAuth tokens for this user to prevent token reuse
                \App\Models\OAuthAccessToken::where('user_id', $existingUser->id)->update(['revoked' => true]);
                \App\Models\OAuthRefreshToken::where('user_id', $existingUser->id)->update(['revoked' => true]);

                // Check if this is an OAuth flow
                if (session('oauth_request')) {
                    return redirect()->route('oauth.authorize', [
                        'client_id' => session('oauth_request')['client_id'],
                        'redirect_uri' => session('oauth_request')['redirect_uri'],
                        'response_type' => 'code',
                        'scope' => session('oauth_request')['scope'] ?? 'openid profile email',
                        'state' => session('oauth_request')['state'] ?? null,
                    ]);
                }

                return redirect()->route('dashboard')->with('success', 'Welcome back!');
            }

            $username = strtolower(explode('@', $email)[0]);
            $password  = 'GoogleOAuth_' . $googleId . '!A1';

            // Store Google user data in session for after verification
            session([
                'google_oauth_name' => $name ?? explode('@', $email)[0],
                'google_oauth_email' => $email,
                'google_oauth_id' => $googleId,
            ]);

            // Create AWS Cognito user (required to send verification code)
            $cognitoCheck = $this->cognitoService->signUp($username, $password, $email);

            if (!$cognitoCheck['success']) {
                // User exists in Cognito — try to send a new OTP code
                $resendResult = $this->cognitoService->resendConfirmationCode($username);

                if (!$resendResult['success']) {
                    // Cognito is unavailable or the user is in an unexpected state
                    // Do NOT authenticate based on error message content
                    // Log the error and redirect with guidance
                    Log::error('Google OAuth - Cognito verification failed', [
                        'email' => $email,
                        'username' => $username,
                        'sign_up_error' => $cognitoCheck['error'] ?? null,
                        'resend_error' => $resendResult['error'] ?? null,
                    ]);

                    session()->forget([
                        'auth_state',
                        'google_oauth_name',
                        'google_oauth_email',
                        'google_oauth_id',
                        'verification_username',
                        'verification_email',
                        'verification_otp_session_id',
                        'verification_code_sent_at',
                        'verification_expires_at',
                    ]);
                    return redirect()->route('auth.login')
                        ->with('error', 'Unable to send verification code. This may be due to a service configuration issue. Please contact support or try again later.');
                }
            }

            $otpSessionId = (string) Str::uuid();
            $expiresAt = now()->addMinutes(30);

            session([
                'auth_state'                       => 'otp_required',
                'verification_username'            => $username,
                'verification_email'               => $email,
                'verification_otp_session_id'      => $otpSessionId,
                'verification_code_sent_at'        => now(),
                'verification_expires_at'          => $expiresAt,
            ]);

            return redirect()->route('auth.verify')
                ->with('info', 'A verification code has been sent to your Gmail. Please enter the 6-digit code.');

        } catch (\Exception $e) {
            session()->forget([
                'auth_state',
                'verification_user_id',
                'verification_username',
                'verification_email',
                'verification_otp_session_id',
                'verification_code_sent_at',
                'verification_expires_at',
            ]);

            return redirect()->route('auth.login')
                ->with('error', 'Google login failed.');
        }
    }

}
