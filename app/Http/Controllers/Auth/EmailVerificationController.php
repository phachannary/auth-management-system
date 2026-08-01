<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CognitoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\User;

class EmailVerificationController extends Controller
{
    protected $cognitoService;

    public function __construct(CognitoService $cognitoService)
    {
        $this->cognitoService = $cognitoService;
    }

    /**
     * Show email verification form
     */
    public function showVerificationForm(Request $request)
    {
        $username = $request->session()->get('verification_username');
        $email = $request->session()->get('verification_email');
        $alreadyConfirmed = $request->session()->get('verification_already_confirmed', false);
        $otpSessionId = $request->session()->get('verification_otp_session_id');
        $expiresAt = $request->session()->get('verification_expires_at');

        if (!$username || !$email) {
            return redirect()->route('auth.login')->with('error', 'Verification session expired. Please login again.');
        }

        return view('auth.verify-email', [
            'username' => $username,
            'email' => $email,
            'alreadyConfirmed' => $alreadyConfirmed,
            'otpSessionId' => $otpSessionId,
            'expiresAtIso' => $expiresAt ? $expiresAt->toIso8601String() : null,
            'isExpired' => $expiresAt ? $expiresAt->isPast() : true,
        ]);
    }

    /**
     * Resend verification code
     */
    public function resendCode(Request $request)
    {
        $username = $request->session()->get('verification_username');

        if (!$username) {
            return redirect()->route('auth.login')->with('error', 'Session expired. Please login again.');
        }

        try {
            $result = $this->cognitoService->resendConfirmationCode($username);

            if ($result['success']) {
                Log::info('Verification code resent to: ' . $username);
                return back()->with('success', 'Verification code sent to your email.');
            } else {
                Log::error('Failed to resend verification code: ' . $result['error']);
                return back()->with('error', 'Failed to send verification code. Please try again.');
            }
        } catch (\Exception $e) {
            Log::error('Exception resending code: ' . $e->getMessage());
            return back()->with('error', 'An error occurred. Please try again.');
        }
    }

    /**
     * Verify email with code
     */
    public function verify(Request $request)
    {
        Log::info('Verification request received', [
            'code' => $request->code,
            'session_username' => $request->session()->get('verification_username'),
            'session_email' => $request->session()->get('verification_email'),
            'google_email' => $request->session()->get('google_oauth_email'),
        ]);

        $request->validate([
            'code' => 'required|string|min:6|max:6'
        ]);

        $username = $request->session()->get('verification_username');

        if (!$username) {
            Log::error('Username not found in session');
            return redirect()->route('auth.login')->with('error', 'Session expired. Please login again.');
        }

        try {
            // Check if user is already confirmed
            $statusResult = $this->cognitoService->getUserStatus($username);
            Log::info('User status check', [
                'username' => $username,
                'status' => $statusResult['status'] ?? 'UNKNOWN',
                'success' => $statusResult['success'] ?? false,
            ]);

            // If user is already confirmed, skip verification and proceed
            if ($statusResult['success'] && $statusResult['status'] === 'CONFIRMED') {
                Log::info('User already confirmed, skipping verification: ' . $username);
                $result = ['success' => true];
            } else {
                Log::info('Calling Cognito confirmSignUp for: ' . $username);
                $result = $this->cognitoService->confirmSignUp($username, $request->code);
                Log::info('Cognito confirmSignUp result', [
                    'success' => $result['success'],
                    'error' => $result['error'] ?? null,
                ]);

                // If confirmSignUp fails with "User cannot be confirmed. Current status is CONFIRMED"
                // it means the user is already confirmed, so we can proceed
                if (!$result['success'] && strpos($result['error'], 'User cannot be confirmed') !== false) {
                    Log::info('User is already confirmed despite status check error, proceeding: ' . $username);
                    $result = ['success' => true];
                }
            }

            if ($result['success']) {
                Log::info('Email verified successfully for: ' . $username);

                // Check if this is a Google OAuth flow
                $googleEmail = $request->session()->get('google_oauth_email');
                // Check if this is a Facebook OAuth flow
                $facebookEmail = $request->session()->get('facebook_oauth_email');

                if ($googleEmail) {
                    // Create local user after Gmail verification
                    $googleName = $request->session()->get('google_oauth_name');
                    $googleId = $request->session()->get('google_oauth_id');

                    Log::info('Google OAuth flow: Creating local user after Gmail verification', [
                        'email' => $googleEmail,
                        'name' => $googleName,
                        'google_id' => $googleId,
                    ]);

                    // Check if user already exists
                    $user = User::where('email', $googleEmail)->first();

                    if (!$user) {
                        try {
                            $user = User::create([
                                'name' => $googleName,
                                'email' => $googleEmail,
                                'google_id' => $googleId,
                                'email_verified_at' => now(),
                                'password' => bcrypt(Str::random(32)),
                            ]);

                            Log::info('Local user created after Gmail verification: ' . $user->email);
                        } catch (\Exception $e) {
                            Log::error('Failed to create local user after Gmail verification: ' . $e->getMessage());
                            return back()->with('error', 'Failed to create account. Please try again.');
                        }
                    } else {
                        Log::info('Local user already exists, updating: ' . $user->email);
                        if (!$user->google_id) {
                            $user->google_id = $googleId;
                            $user->email_verified_at = now();
                            $user->save();
                        }
                    }

                    // Clear Google OAuth session data
                    $request->session()->forget(['google_oauth_name', 'google_oauth_email', 'google_oauth_id']);
                } elseif ($facebookEmail) {
                    // Create local user after Facebook verification
                    $facebookName = $request->session()->get('facebook_oauth_name');
                    $facebookId = $request->session()->get('facebook_oauth_id');

                    Log::info('Facebook OAuth flow: Creating local user after email verification', [
                        'email' => $facebookEmail,
                        'name' => $facebookName,
                        'facebook_id' => $facebookId,
                    ]);

                    // Check if user already exists
                    $user = User::where('email', $facebookEmail)->first();

                    if (!$user) {
                        try {
                            $user = User::create([
                                'name' => $facebookName,
                                'email' => $facebookEmail,
                                'facebook_id' => $facebookId,
                                'email_verified_at' => now(),
                                'password' => bcrypt(Str::random(32)),
                            ]);

                            Log::info('Local user created after Facebook verification: ' . $user->email);
                        } catch (\Exception $e) {
                            Log::error('Failed to create local user after Facebook verification: ' . $e->getMessage());
                            return back()->with('error', 'Failed to create account. Please try again.');
                        }
                    } else {
                        Log::info('Local user already exists, updating: ' . $user->email);
                        if (!$user->facebook_id) {
                            $user->facebook_id = $facebookId;
                            $user->email_verified_at = now();
                            $user->save();
                        }
                    }

                    // Clear Facebook OAuth session data
                    $request->session()->forget(['facebook_oauth_name', 'facebook_oauth_email', 'facebook_oauth_id']);
                } else {
                    // Regular registration flow - find existing user
                    $email = $request->session()->get('verification_email');
                    $user = User::where('email', $email)->first();

                    if ($user) {
                        $user->email_verified_at = now();
                        $user->save();
                    }
                }

                if ($user) {
                    // Log the user in
                    Auth::login($user);
                    Log::info('User logged in after email verification: ' . $user->email);

                    // Clear verification session
                    $request->session()->forget(['verification_username', 'verification_email']);

                    return redirect()->route('dashboard')->with('success', 'Email verified successfully! Welcome to your dashboard.');
                } else {
                    Log::error('User not found after verification');
                    // Clear verification session
                    $request->session()->forget(['verification_username', 'verification_email']);

                    return redirect()->route('auth.login')->with('error', 'Account not found. Please register again.');
                }
            } else {
                Log::error('Email verification failed: ' . $result['error']);
                Log::error('Returning to verification page with error');
                return back()->with('error', 'Invalid verification code. Please try again.');
            }
        } catch (\Exception $e) {
            Log::error('Exception during verification: ' . $e->getMessage());
            return back()->with('error', 'An error occurred. Please try again.');
        }
    }
}
