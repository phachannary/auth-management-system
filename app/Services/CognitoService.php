<?php

namespace App\Services;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Str;

class CognitoService
{
    protected $client;
    protected $userPoolId;
    protected $clientId;
    protected $clientSecret;
    protected $region;

    public function __construct()
    {
        $this->clientId = env('COGNITO_CLIENT_ID');
        $this->clientSecret = env('COGNITO_CLIENT_SECRET');
        $this->region = env('COGNITO_REGION', 'ap-southeast-2');
        $this->userPoolId = env('COGNITO_USER_POOL_ID');

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
        }

        $this->client = new CognitoIdentityProviderClient($config);
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
    public function getHostedUIUrl($redirectUri = null)
    {
        $domain = env('COGNITO_USER_POOL_DOMAIN');

        if (!$domain) {
            throw new \Exception('COGNITO_USER_POOL_DOMAIN not set');
        }

        $redirectUri = $redirectUri ?: env('APP_URL') . '/auth/callback';

        return "https://{$domain}.auth.ap-southeast-2.amazoncognito.com/oauth2/authorize?"
            . http_build_query([
                'client_id' => $this->clientId,
                'response_type' => 'code',
                'scope' => 'email openid profile',
                'redirect_uri' => $redirectUri,
            ]);
    }

    /* ---------------------------
     | EXCHANGE CODE FOR TOKEN
    ----------------------------*/
    public function exchangeCodeForTokens($code, $redirectUri)
    {
        try {
            $domain = env('COGNITO_USER_POOL_DOMAIN');

            $url = "https://{$domain}.auth.ap-southeast-2.amazoncognito.com/oauth2/token";

            $client = new \GuzzleHttp\Client();

            $response = $client->post($url, [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->clientId,
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'data' => $data
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
