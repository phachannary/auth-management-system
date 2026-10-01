<?php

namespace Tests\Feature\OAuth;

use App\Models\OAuthClient;
use App\Models\User;
use App\Services\JwtService;

/**
 * Shared helpers for OAuth feature tests: a confidential DS1 test client and
 * shortcuts for obtaining codes and tokens through the real endpoints.
 */
trait InteractsWithOAuth
{
    protected function createDs1Client(): OAuthClient
    {
        return OAuthClient::create([
            'name' => 'DS1 Test Client',
            'client_id' => 'ds1_test_client',
            'client_secret' => 'test_secret',
            'redirect_uris' => ['http://localhost:3000/auth/callback'],
            'scopes' => ['openid', 'profile', 'email'],
            'confidential' => true,
            'active' => true,
        ]);
    }

    protected function issueCode(User $user): string
    {
        $response = $this->actingAs($user)->get('/oauth/authorize?' . http_build_query([
            'client_id' => 'ds1_test_client',
            'redirect_uri' => 'http://localhost:3000/auth/callback',
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => 'state-123',
        ]));
        $response->assertStatus(302);

        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query['code'];
    }

    protected function exchangeCode(User $user): array
    {
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $this->issueCode($user),
            'redirect_uri' => 'http://localhost:3000/auth/callback',
            'client_id' => 'ds1_test_client',
            'client_secret' => 'test_secret',
        ]);
        $response->assertOk();

        return $response->json();
    }

    protected function refresh(string $refreshToken, array $overrides = [])
    {
        return $this->postJson('/api/oauth/token', array_merge([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => 'ds1_test_client',
            'client_secret' => 'test_secret',
        ], $overrides));
    }

    protected function claims(string $jwt): array
    {
        $result = app(JwtService::class)->validateToken($jwt);
        $this->assertTrue($result['success'], 'Token failed validation');

        return $result['data'];
    }
}
