<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            if (Auth::check()) {
                Log::info('Google OAuth callback skipped because user is already authenticated', [
                    'user_id' => Auth::id(),
                    'redirect' => 'dashboard',
                ]);

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

            $username = strtolower(explode('@', $email)[0]);
            $password  = 'GoogleOAuth_' . $googleId . '!A1';

            // Store Google user data in session for after verification
            session([
                'google_oauth_name' => $name ?? explode('@', $email)[0],
                'google_oauth_email' => $email,
                'google_oauth_id' => $googleId,
            ]);

            Log::info('Google OAuth: Creating AWS Cognito user to send verification code', ['email' => $email]);

            // Create AWS Cognito user (required to send verification code)
            $cognitoCheck = $this->cognitoService->signUp($username, $password, $email);

            $alreadyConfirmedInCognito = false;

            if (!$cognitoCheck['success']) {
                Log::info('Cognito signUp skipped (user exists); trying to send fresh OTP', [
                    'email'    => $email,
                    'username' => $username,
                    'error'    => $cognitoCheck['error'] ?? null,
                ]);

                // User exists in Cognito — try to send a new OTP code
                $resendResult = $this->cognitoService->resendConfirmationCode($username);

                if (!$resendResult['success']) {
                    if ($this->isAlreadyConfirmed($resendResult['error'] ?? null)) {
                        $alreadyConfirmedInCognito = true;
                        Log::info('Cognito user is already confirmed', ['email' => $email]);
                    } else {
                        Log::warning('Unable to send OTP after Google OAuth', [
                            'email'    => $email,
                            'username' => $username,
                            'error'    => $resendResult['error'] ?? null,
                        ]);

                        session()->forget(['auth_state']);
                        return redirect()->route('auth.login')
                            ->with('error', 'Unable to send verification code. Please try again or contact support.');
                    }
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
                'verification_already_confirmed'   => $alreadyConfirmedInCognito,
            ]);

            Log::info('Google OAuth: Redirecting to verification form', [
                'email' => $email,
                'username' => $username,
            ]);

            $flashMessage = $alreadyConfirmedInCognito
                ? 'Your email was previously verified. Click the button below to confirm and access your dashboard.'
                : 'A verification code has been sent to your Gmail. Please enter the 6-digit code.';

            return redirect()->route('auth.verification.form')
                ->with('info', $flashMessage);

        } catch (\Exception $e) {
            Log::error('Google login error: ' . $e->getMessage());
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

    private function isAlreadyConfirmed(?string $error): bool
    {
        if (!$error) {
            return false;
        }

        return str_contains($error, 'CONFIRMED') || str_contains($error, 'already confirmed');
    }
}
