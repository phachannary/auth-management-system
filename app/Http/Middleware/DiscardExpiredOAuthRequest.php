<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Drops a pending OAuth authorization request from the session once it has
 * expired, so an abandoned client login (e.g. DS1) cannot redirect a later,
 * unrelated password/Google/Facebook login back to that client.
 *
 * Requests without an expiry (stored before expiry was introduced) are
 * treated as stale.
 */
class DiscardExpiredOAuthRequest
{
    public function handle(Request $request, Closure $next)
    {
        $pending = $request->session()->get('oauth_request');

        if ($pending !== null) {
            $expiresAt = is_array($pending) ? ($pending['expires_at'] ?? null) : null;

            if (!is_int($expiresAt) || $expiresAt <= now()->timestamp) {
                $request->session()->forget('oauth_request');
            }
        }

        return $next($request);
    }
}
