<?php

namespace App\Services;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;

class CognitoService
{
    protected $client;
    protected $userPoolId;
    protected $clientId;
    protected $clientSecret;
    protected $region;
    protected $domain;

    public function __construct()
    {
        $this->clientId = config('services.cognito.client_id', env('COGNITO_CLIENT_ID'));
        $this->clientSecret = config('services.cognito.client_secret', env('COGNITO_CLIENT_SECRET'));
        $this->region = config('services.cognito.region', env('COGNITO_REGION', 'ap-southeast-2'));
        $this->userPoolId = config('services.cognito.user_pool_id', env('COGNITO_USER_POOL_ID'));
        $this->domain = config('services.cognito.domain', env('COGNITO_USER_POOL_DOMAIN'));

        $credentials = null;

        if (env('AWS_ACCESS_KEY_ID') && env('AWS_SECRET_ACCESS_KEY')) {
            $credentials = [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ];

            if (env('AWS_SESSION_TOKEN')) {
                $credentials['token'] = env('AWS_SESSION_TOKEN');
            }
        }

        $config = [
            'version' => 'latest',
            'region'  => $this->region,
        ];

        if ($credentials) {
            $config['credentials'] = $credentials;
        } else {
            // Disable instance profile and other credential providers
            $config['credentials'] = false;
        }

        $this->client = new CognitoIdentityProviderClient($config);
    }

    /**
     * Get the full Cognito domain URL
     */
    public function getCognitoDomainUrl(): string
    {
        return "https://{$this->domain}.auth.{$this->region}.amazoncognito.com";
    }

    /* ---------------------------
     | AUTH - USER LOGIN
    ----------------------------*/
    public function initiateAuth($username, $password)
    {
        try {
            $params = [
                'AuthFlow' => 'USER_PASSWORD_AUTH',
                'ClientId' => $this->clientId,
                'AuthParameters' => [
                    'USERNAME' => $username,
                    'PASSWORD' => $password,
                ],
            ];

            if (!empty($this->clientSecret)) {
                $params['AuthParameters']['SECRET_HASH'] =
                    $this->calculateSecretHash($username);
            }

            $result = $this->client->initiateAuth($params);

            return [
                'success' => true,
                'data' => $result->toArray()
            ];

        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => $e->getAwsErrorMessage()
            ];
        }
    }

    /* ---------------------------
     | SIGN UP
    ----------------------------*/
    public function signUp($username, $password, $email)
    {
        try {
            $params = [
                'ClientId' => $this->clientId,
                'Username' => $username,
                'Password' => $password,
                'UserAttributes' => [
                    [
                        'Name' => 'email',
                        'Value' => $email,
                    ],
                ],
            ];

            if (!empty($this->clientSecret)) {
                $params['SecretHash'] = $this->calculateSecretHash($username);
            }

            $result = $this->client->signUp($params);

            return [
                'success' => true,
                'data' => $result->toArray()
            ];

        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => $e->getAwsErrorMessage()
            ];
        }
    }

    /* ---------------------------
     | CONFIRM SIGN UP
    ----------------------------*/
    public function confirmSignUp($username, $code)
    {
        try {
            \Log::info("Cognito confirmSignUp - Username: $username, Code: $code, ClientId: $this->clientId");

            $params = [
                'ClientId' => $this->clientId,
                'Username' => $username,
                'ConfirmationCode' => $code,
            ];

            // IMPORTANT: add SecretHash if you use client secret
            if (!empty($this->clientSecret)) {
                $params['SecretHash'] = $this->calculateSecretHash($username);
                \Log::info("SecretHash added for username: $username");
            }

            \Log::info("Calling confirmSignUp with params: " . json_encode($params));

            $result = $this->client->confirmSignUp($params);

            \Log::info("confirmSignUp successful for username: $username");

            return [
                'success' => true,
                'data' => $result->toArray(),
            ];

        } catch (AwsException $e) {
            \Log::error("confirmSignUp failed for username: $username - " . $e->getAwsErrorMessage());
            \Log::error("AWS Error Code: " . $e->getAwsErrorCode());
            return [
                'success' => false,
                'error' => $e->getAwsErrorMessage(),
            ];
        } catch (\Exception $e) {
            \Log::error("confirmSignUp exception for username: $username - " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /* ---------------------------
     | CHECK USER STATUS
    ----------------------------*/
    public function getUserStatus($username)
    {
        try {
            $result = $this->client->adminGetUser([
                'UserPoolId' => $this->userPoolId,
                'Username' => $username,
            ]);

            return [
                'success' => true,
                'status' => $result['UserStatus'] ?? 'UNKNOWN',
                'enabled' => $result['Enabled'] ?? false,
            ];

        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => $e->getAwsErrorMessage(),
            ];
        }
    }

    /* ---------------------------
     | RESEND CODE
    ----------------------------*/
    public function resendConfirmationCode($username)
    {
        try {
            $params = [
                'ClientId' => $this->clientId,
                'Username' => $username,
            ];

            if (!empty($this->clientSecret)) {
                $params['SecretHash'] =
                    $this->calculateSecretHash($username);
            }

            $result = $this->client->resendConfirmationCode($params);

            return [
                'success' => true,
                'data' => $result->toArray()
            ];

        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => $e->getAwsErrorMessage()
            ];
        }
    }

    /* ---------------------------
     | GET USER (ACCESS TOKEN)
    ----------------------------*/
    public function getUser($accessToken)
    {
        try {
            $result = $this->client->getUser([
                'AccessToken' => $accessToken,
            ]);

            return [
                'success' => true,
                'data' => $result->toArray()
            ];

        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => $e->getAwsErrorMessage()
            ];
        }
    }

    /* ---------------------------
     | REFRESH TOKEN
    ----------------------------*/
    public function refreshToken($refreshToken)
    {
        try {
            $result = $this->client->initiateAuth([
                'AuthFlow' => 'REFRESH_TOKEN_AUTH',
                'ClientId' => $this->clientId,
                'AuthParameters' => [
                    'REFRESH_TOKEN' => $refreshToken,
                ],
            ]);

            return [
                'success' => true,
                'data' => $result->toArray()
            ];

        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => $e->getAwsErrorMessage()
            ];
        }
    }

    /* ---------------------------
     | SECRET HASH
    ----------------------------*/
    private function calculateSecretHash($username)
    {
        return base64_encode(
            hash_hmac('sha256', $username . $this->clientId, $this->clientSecret, true)
        );
    }

    /* ---------------------------
     | HOSTED UI URL
    ----------------------------*/
    public function getHostedUIUrl($redirectUri = null, $identityProvider = null)
    {
        if (!$this->domain) {
            throw new \Exception('COGNITO_USER_POOL_DOMAIN not set');
        }

        $redirectUri = $redirectUri ?: env('APP_URL') . '/auth/cognito/callback';

        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'scope' => 'email openid phone',
            'redirect_uri' => $redirectUri,
        ];

        // Skip Hosted UI and go directly to a specific identity provider
        if ($identityProvider) {
            $params['identity_provider'] = $identityProvider;
        }

        return $this->getCognitoDomainUrl() . '/oauth2/authorize?'
            . http_build_query($params);
    }

    /* ---------------------------
     | EXCHANGE CODE FOR TOKEN
    ----------------------------*/
    public function exchangeCodeForTokens($code, $redirectUri = null)
    {
        try {
            $redirectUri = $redirectUri ?: env('APP_URL') . '/auth/cognito/callback';
            $url = $this->getCognitoDomainUrl() . '/oauth2/token';

            $params = [
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId,
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ];

            // Include client_secret for confidential clients
            if (!empty($this->clientSecret)) {
                $params['client_secret'] = $this->clientSecret;
            }

            $response = Http::asForm()->post($url, $params);

            if (!$response->successful()) {
                \Log::error('Token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'error' => 'Token exchange failed: ' . $response->body(),
                ];
            }

            return [
                'success' => true,
                'data' => $response->json()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /* ---------------------------
     | GLOBAL SIGN OUT
    ----------------------------*/
    public function globalSignOut($accessToken)
    {
        try {
            $this->client->globalSignOut([
                'AccessToken' => $accessToken,
            ]);

            return ['success' => true];

        } catch (AwsException $e) {
            \Log::error('Global sign out failed: ' . $e->getAwsErrorMessage());
            return [
                'success' => false,
                'error' => $e->getAwsErrorMessage(),
            ];
        }
    }

    /* ---------------------------
     | COGNITO LOGOUT URL
    ----------------------------*/
    public function getLogoutUrl($redirectUri = null)
    {
        $redirectUri = $redirectUri ?: env('APP_URL') . '/auth/login';

        return $this->getCognitoDomainUrl() . '/logout?'
            . http_build_query([
                'client_id' => $this->clientId,
                'logout_uri' => $redirectUri,
            ]);
    }

    /* ---------------------------
     | VALIDATE ID TOKEN (JWT/JWKS)
    ----------------------------*/
    public function validateIdToken($idToken)
    {
        try {
            $jwks = $this->getJwks();
            $keys = JWK::parseKeySet($jwks);

            $decoded = JWT::decode($idToken, $keys);

            // Verify issuer
            $expectedIssuer = "https://cognito-idp.{$this->region}.amazonaws.com/{$this->userPoolId}";
            if ($decoded->iss !== $expectedIssuer) {
                return ['success' => false, 'error' => 'Invalid issuer'];
            }

            // Verify audience (client_id)
            if ($decoded->aud !== $this->clientId) {
                return ['success' => false, 'error' => 'Invalid audience'];
            }

            // Verify token_use
            if (($decoded->token_use ?? null) !== 'id') {
                return ['success' => false, 'error' => 'Invalid token_use'];
            }

            // Token expiration is checked by JWT::decode automatically

            return [
                'success' => true,
                'data' => (array) $decoded,
            ];

        } catch (\Exception $e) {
            \Log::error('ID token validation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /* ---------------------------
     | VALIDATE ACCESS TOKEN (JWT/JWKS)
    ----------------------------*/
    public function validateAccessToken($accessToken)
    {
        try {
            $jwks = $this->getJwks();
            $keys = JWK::parseKeySet($jwks);

            $decoded = JWT::decode($accessToken, $keys);

            // Verify issuer
            $expectedIssuer = "https://cognito-idp.{$this->region}.amazonaws.com/{$this->userPoolId}";
            if ($decoded->iss !== $expectedIssuer) {
                return ['success' => false, 'error' => 'Invalid issuer'];
            }

            // Verify token_use
            if (($decoded->token_use ?? null) !== 'access') {
                return ['success' => false, 'error' => 'Invalid token_use'];
            }

            return [
                'success' => true,
                'data' => (array) $decoded,
            ];

        } catch (\Exception $e) {
            \Log::error('Access token validation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /* ---------------------------
     | GET JWKS (cached)
    ----------------------------*/
    private function getJwks(): array
    {
        $cacheKey = "cognito_jwks_{$this->userPoolId}";

        return Cache::remember($cacheKey, 3600, function () {
            $url = "https://cognito-idp.{$this->region}.amazonaws.com/{$this->userPoolId}/.well-known/jwks.json";
            $response = Http::get($url);

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch JWKS from Cognito');
            }

            return $response->json();
        });
    }

    /* ---------------------------
     | GET USER INFO FROM TOKENS
    ----------------------------*/
    public function getUserInfoFromToken($accessToken)
    {
        try {
            $url = $this->getCognitoDomainUrl() . '/oauth2/userInfo';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get($url);

            if (!$response->successful()) {
                return ['success' => false, 'error' => 'Failed to get user info'];
            }

            return [
                'success' => true,
                'data' => $response->json(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
