<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;

class JwtService
{
    private string $secretKey;
    private string $algorithm;

    public function __construct()
    {
        $this->algorithm = 'HS256';
        $this->secretKey = $this->getSecretKey();
    }

    private function getSecretKey(): string
    {
        $keyPath = storage_path('app/oauth_secret.key');
        
        if (!file_exists($keyPath)) {
            $this->generateSecretKey();
        }
        
        return file_get_contents($keyPath);
    }

    private function generateSecretKey(): void
    {
        $secretKey = base64_encode(random_bytes(64));
        
        storage_path('app');
        file_put_contents(storage_path('app/oauth_secret.key'), $secretKey);
        
        chmod(storage_path('app/oauth_secret.key'), 0600);
    }

    public function generateAccessToken(array $payload): string
    {
        $now = time();
        
        $tokenPayload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + (60 * 60), // 1 hour
            'type' => 'access_token',
        ]);

        return JWT::encode($tokenPayload, $this->secretKey, $this->algorithm);
    }

    public function generateRefreshToken(array $payload): string
    {
        $now = time();
        
        $tokenPayload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + (60 * 60 * 24 * 30), // 30 days
            'type' => 'refresh_token',
        ]);

        return JWT::encode($tokenPayload, $this->secretKey, $this->algorithm);
    }

    public function generateIdToken(array $payload): string
    {
        $now = time();
        
        $tokenPayload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + (60 * 60), // 1 hour
            'type' => 'id_token',
            'iss' => config('app.url'),
            'aud' => $payload['client_id'] ?? null,
        ]);

        return JWT::encode($tokenPayload, $this->secretKey, $this->algorithm);
    }

    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return [
                'success' => true,
                'data' => (array) $decoded,
            ];
        } catch (\Exception $e) {
            Log::error('JWT validation failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getJwks(): array
    {
        // For HS256, we don't use JWKS. Return a simple response indicating HS256.
        // Clients should use the shared secret for validation.
        return [
            'keys' => [
                [
                    'kty' => 'oct',
                    'alg' => 'HS256',
                    'use' => 'sig',
                    'kid' => 'oauth-key-1',
                    'k' => $this->base64UrlEncode($this->secretKey),
                ]
            ]
        ];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
