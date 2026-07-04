<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CognitoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class CognitoLoginController extends Controller
{
    protected $cognitoService;

    public function __construct(CognitoService $cognitoService)
    {
        $this->cognitoService = $cognitoService;
    }

    public function redirectToCognito()
    {
        $url = $this->cognitoService->getHostedUIUrl(route('auth.cognito.callback'));

        return redirect($url);
    }

    public function handleCognitoCallback(Request $request)
    {
        try {
            $code = $request->get('code');

            if (!$code) {
                return redirect()->route('auth.login')->with('error', 'No code received');
            }

            $tokenResult = $this->cognitoService->exchangeCodeForTokens(
                $code,
                route('auth.cognito.callback')
            );

            if (!$tokenResult['success']) {
                return redirect()->route('auth.login')->with('error', $tokenResult['error']);
            }

            $accessToken = $tokenResult['data']['access_token'] ?? null;

            if (!$accessToken) {
                return redirect()->route('auth.login')->with('error', 'Invalid token');
            }

            $userResult = $this->cognitoService->getUser($accessToken);

            if (!$userResult['success']) {
                return redirect()->route('auth.login')->with('error', 'User fetch failed');
            }

            $email = $this->extractEmail($userResult['data']);

            if (!$email) {
                return redirect()->route('auth.login')->with('error', 'No email found');
            }

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => explode('@', $email)[0],
                    'password' => bcrypt('cognito_' . time()),
                    'email_verified_at' => now(),
                ]
            );

            Auth::login($user, true);

            return redirect()->route('dashboard');

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->route('auth.login')->with('error', 'Login failed');
        }
    }

    private function extractEmail($data)
    {
        foreach ($data['UserAttributes'] ?? [] as $attr) {
            if ($attr['Name'] === 'email') {
                return $attr['Value'];
            }
        }
        return null;
    }
}
