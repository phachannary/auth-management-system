<?php

namespace Tests\Feature\OAuth;

use App\Models\OAuthAccessToken;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Access token claims and the oauth.token middleware (exercised through the
 * /api/oauth/test route, which is protected by it).
 */
class AccessTokenTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithOAuth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createDs1Client();
    }

    // --- claims -----------------------------------------------------------

    public function test_access_token_has_issuer_audience_and_unique_id()
    {
        $user = User::factory()->create();
        $tokens = $this->exchangeCode($user);

        $claims = $this->claims($tokens['access_token']);

        $this->assertSame(config('app.url'), $claims['iss']);
        $this->assertSame('ds1_test_client', $claims['aud']);
        $this->assertSame('access_token', $claims['type']);
        $this->assertSame((string) $user->id, $claims['sub']);
        $this->assertIsString($claims['jti']);
        $this->assertNotSame('', $claims['jti']);
    }

    public function test_tokens_generated_with_identical_claims_are_still_unique()
    {
        $jwt = app(JwtService::class);
        $payload = ['sub' => '1', 'email' => 'a@example.com', 'name' => 'A', 'client_id' => 'ds1_test_client'];

        $first = $jwt->generateAccessToken($payload);
        $second = $jwt->generateAccessToken($payload);
        $this->assertNotSame($first, $second);
        $this->assertNotSame($this->claims($first)['jti'], $this->claims($second)['jti']);

        $firstRefresh = $jwt->generateRefreshToken(['sub' => '1', 'client_id' => 'ds1_test_client']);
        $secondRefresh = $jwt->generateRefreshToken(['sub' => '1', 'client_id' => 'ds1_test_client']);
        $this->assertNotSame($firstRefresh, $secondRefresh);
    }

    // --- oauth.token middleware -------------------------------------------

    public function test_middleware_accepts_valid_access_token()
    {
        $user = User::factory()->create();
        $tokens = $this->exchangeCode($user);

        $this->withToken($tokens['access_token'])
            ->getJson('/api/oauth/test')
            ->assertOk()
            ->assertJson([
                'user_id' => (string) $user->id,
                'client_id' => 'ds1_test_client',
            ]);
    }

    public function test_middleware_rejects_revoked_access_token()
    {
        $user = User::factory()->create();
        $tokens = $this->exchangeCode($user);

        OAuthAccessToken::query()->update(['revoked' => true]);

        $this->withToken($tokens['access_token'])
            ->getJson('/api/oauth/test')
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_token']);
    }

    public function test_middleware_rejects_access_token_revoked_by_refresh_rotation()
    {
        $user = User::factory()->create();
        $tokens = $this->exchangeCode($user);

        $this->refresh($tokens['refresh_token'])->assertOk();

        $this->withToken($tokens['access_token'])
            ->getJson('/api/oauth/test')
            ->assertStatus(401);
    }

    public function test_middleware_rejects_access_token_expired_in_database()
    {
        $user = User::factory()->create();
        $tokens = $this->exchangeCode($user);

        OAuthAccessToken::query()->update(['expires_at' => now()->subMinute()]);

        $this->withToken($tokens['access_token'])
            ->getJson('/api/oauth/test')
            ->assertStatus(401);
    }

    public function test_middleware_rejects_validly_signed_token_with_no_database_record()
    {
        $token = app(JwtService::class)->generateAccessToken([
            'sub' => '1',
            'email' => 'a@example.com',
            'name' => 'A',
            'client_id' => 'ds1_test_client',
        ]);

        $this->withToken($token)
            ->getJson('/api/oauth/test')
            ->assertStatus(401);
    }

    public function test_middleware_rejects_missing_malformed_and_non_access_tokens()
    {
        $user = User::factory()->create();
        $tokens = $this->exchangeCode($user);

        $this->getJson('/api/oauth/test')->assertStatus(401);
        $this->withToken('not-a-jwt')->getJson('/api/oauth/test')->assertStatus(401);
        $this->withToken($tokens['refresh_token'])->getJson('/api/oauth/test')->assertStatus(401);
        $this->withToken($tokens['id_token'])->getJson('/api/oauth/test')->assertStatus(401);
    }
}
