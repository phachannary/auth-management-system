<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Str;

class CognitoController extends Controller
{
    private $cognitoDomain;
    private $clientId;
    private $redirectUri;

    public function __construct()
    {
        $this->cognitoDomain = env('COGNITO_DOMAIN');
        $this->clientId = env('COGNITO_CLIENT_ID');
        $this->redirectUri = env('APP_URL') . '/auth/callback';
    }

    public function login()
    {
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'scope' => 'email openid profile',
            'redirect_uri' => $this->redirectUri,
        ];

        return redirect($this->cognitoDomain . '/oauth2/authorize?' . http_build_query($params));
    }

    public function callback(Request $request)
    {
        try {
            $code = $request->get('code');

            if (!$code) {
                return redirect('/login')->with('error', 'Authorization failed');
            }

            $tokenResponse = $this->exchangeCodeForTokens($code);
            $userInfo = $this->getUserInfo($tokenResponse['access_token']);

            $email = $userInfo['email'] ?? null;

            if (!$email) {
                throw new \Exception("Email not provided by Cognito");
            }

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $userInfo['name'] ?? explode('@', $email)[0],
                    'password' => bcrypt(Str::random(32)),
                    'email_verified_at' => now(),
                ]
            );

            Auth::login($user, true);

            return redirect('/dashboard')->with('success', 'Login successful');

        } catch (\Exception $e) {
            return redirect('/login')->with('error', $e->getMessage());
        }
    }

    private function exchangeCodeForTokens($code)
    {
        $response = Http::asForm()->post($this->cognitoDomain . '/oauth2/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Token exchange failed');
        }

        return $response->json();
    }

    private function getUserInfo($accessToken)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get($this->cognitoDomain . '/oauth2/userInfo');

        if (!$response->successful()) {
            throw new \Exception('Failed to get user info');
        }

        return $response->json();
    }
}
