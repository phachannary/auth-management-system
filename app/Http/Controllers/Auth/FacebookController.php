<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\CognitoService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FacebookController extends Controller
{
    protected $cognitoService;

    public function __construct(CognitoService $cognitoService)
    {
        $this->cognitoService = $cognitoService;
    }

    /**
     * Redirect the user to the Facebook authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')
            ->with(['auth_type' => 'rerequest'])
            ->redirect();
    }

    /**
     * Handle Facebook OAuth callback - skip verification for existing verified users
     */
    public function handleFacebookCallback()
    {
        try {
            $facebookUser = Socialite::driver('facebook')->user();
            $facebookId = $facebookUser->id;
            $email = $facebookUser->email;
            $name = $facebookUser->name;

            // SAFETY: Facebook may not return email
            if (!$email) {
                return redirect()->route('auth.login')
                    ->withErrors(['login' => 'Facebook did not provide email. Please use another login method.']);
            }

            // Check if user already exists in local database with Facebook ID
            $existingUser = User::where('facebook_id', $facebookId)->first();

            if ($existingUser && $existingUser->email_verified_at) {
                // User exists and is verified - log them in directly
                Auth::login($existingUser);
                Session::regenerate();
                return redirect()->route('dashboard')->with('success', 'Welcome back!');
            }

            // New user or unverified user - proceed with verification flow
            $username = 'fb_' . strtolower(preg_replace('/[^a-z0-9]/', '', $email));
            $password  = 'FacebookOAuth_' . $facebookId . '!A1';

            // Store Facebook user data in session for after verification
            session([
                'facebook_oauth_name' => $name ?? $email,
                'facebook_oauth_email' => $email,
                'facebook_oauth_id' => $facebookId,
            ]);

            // Create AWS Cognito user (required to send verification code)
            $cognitoCheck = $this->cognitoService->signUp($username, $password, $email);

            $alreadyConfirmedInCognito = false;

            if (!$cognitoCheck['success']) {

                // User exists in Cognito — try to send a new OTP code
                $resendResult = $this->cognitoService->resendConfirmationCode($username);

                if (!$resendResult['success']) {
                    if ($this->isAlreadyConfirmed($resendResult['error'] ?? null)) {
                        $alreadyConfirmedInCognito = true;
                    } else {
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

            $flashMessage = $alreadyConfirmedInCognito
                ? 'Your email was previously verified. Click the button below to confirm and access your dashboard.'
                : 'A verification code has been sent to your email. Please enter the 6-digit code.';

            return redirect()->route('auth.verify')
                ->with('info', $flashMessage);

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
                ->with('error', 'Facebook login failed.');
        }
    }

    private function isAlreadyConfirmed(?string $error): bool
    {
        if (!$error) {
            return false;
        }

        $alreadyConfirmedMessages = [
            'User is already confirmed',
            'NotAuthorizedException',
            'AliasExistsException',
        ];

        foreach ($alreadyConfirmedMessages as $message) {
            if (strpos($error, $message) !== false) {
                return true;
            }
        }

        return false;
    }
}
