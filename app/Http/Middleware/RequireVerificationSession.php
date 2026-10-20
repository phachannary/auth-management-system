<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Ensures the verification endpoint can only be accessed with a valid verification session.
 * This prevents attackers from POSTing directly to /auth/verify with arbitrary usernames and codes.
 *
 * For POST /auth/verify: Requires verification session (username or email in session)
 * For GET /auth/verify: Allows legacy flow where username comes from old input after failed verification
 */
class RequireVerificationSession
{
    public function handle(Request $request, Closure $next)
    {
        // For POST requests, strictly require verification session data
        if ($request->isMethod('POST')) {
            $username = $request->session()->get('verification_username');
            $email = $request->session()->get('verification_email');

            if (!$username && !$email) {
                return redirect()->route('auth.login')
                    ->with('error', 'Verification session expired. Please login again.');
            }

            // Check if verification session has expired
            $expiresAt = $request->session()->get('verification_expires_at');
            $isOAuthFlow = $request->session()->get('google_oauth_email') || $request->session()->get('facebook_oauth_email');

            // Handle both Carbon objects and timestamps
            if ($expiresAt) {
                if (is_numeric($expiresAt)) {
                    $expiresAt = \Carbon\Carbon::createFromTimestamp($expiresAt);
                }
                if (now()->gt($expiresAt) && !$isOAuthFlow) {
                    $request->session()->forget([
                        'verification_username',
                        'verification_email',
                        'verification_expires_at',
                        'verification_otp_session_id',
                        'verification_code_sent_at',
                    ]);
                    return redirect()->route('auth.login')
                        ->with('error', 'Your verification session has expired. Please register again or request a new verification code.');
                }
            }
        }

        return $next($request);
    }
}
