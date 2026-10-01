<?php

namespace Tests\Feature\OAuth;

use App\Models\OAuthAccessToken;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshTokenGrantTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithOAuth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createDs1Client();
    }

    public function test_valid_refresh_token_issues_new_tokens_and_rotates_the_old_ones()
    {
        $user = User::factory()->create();
        $original = $this->exchangeCode($user);

        // Refreshing immediately (usually within the same second) used to
        // produce byte-identical JWTs and violate the unique token index.
        $response = $this->refresh($original['refresh_token']);

        $response->assertOk();
        $response->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'refresh_token', 'id_token']);
        $refreshed = $response->json();

        $this->assertNotSame($original['access_token'], $refreshed['access_token']);
        $this->assertNotSame($original['refresh_token'], $refreshed['refresh_token']);
        $this->assertNotSame($this->claims($original['access_token'])['jti'], $this->claims($refreshed['access_token'])['jti']);

        // Old pair revoked, new pair active
        $this->assertTrue(OAuthAccessToken::where('token', hash('sha256', $original['access_token']))->value('revoked'));
        $this->assertTrue(OAuthRefreshToken::where('token', hash('sha256', $original['refresh_token']))->value('revoked'));
        $this->assertFalse(OAuthAccessToken::where('token', hash('sha256', $refreshed['access_token']))->value('revoked'));
        $this->assertSame(2, OAuthAccessToken::count());
        $this->assertSame(2, OAuthRefreshToken::count());

        // Scopes carried over to the new access token
        $this->assertSame(
            ['openid', 'profile', 'email'],
            OAuthAccessToken::where('token', hash('sha256', $refreshed['access_token']))->first()->scopes
        );

        // New access token works; old one no longer does
        $this->withToken($refreshed['access_token'])->getJson('/api/oauth/userinfo')
            ->assertOk()
            ->assertJson(['sub' => (string) $user->id]);
        $this->withToken($original['access_token'])->getJson('/api/oauth/userinfo')->assertStatus(401);
    }

    public function test_refresh_tokens_can_be_chained()
    {
        $user = User::factory()->create();
        $tokens = $this->exchangeCode($user);

        for ($i = 0; $i < 3; $i++) {
            $response = $this->refresh($tokens['refresh_token']);
            $response->assertOk();
            $tokens = $response->json();
        }

        $this->assertSame(4, OAuthAccessToken::count());
        $this->assertSame(1, OAuthAccessToken::where('revoked', false)->count());
        $this->assertSame(1, OAuthRefreshToken::where('revoked', false)->count());
    }

    public function test_rotated_refresh_token_cannot_be_reused()
    {
        $user = User::factory()->create();
        $original = $this->exchangeCode($user);

        $refreshed = $this->refresh($original['refresh_token'])->assertOk()->json();

        $this->refresh($original['refresh_token'])
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid_grant']);

        // The legitimately rotated token is unaffected
        $this->refresh($refreshed['refresh_token'])->assertOk();
    }

    public function test_invalid_refresh_token_is_rejected()
    {
        $user = User::factory()->create();
        $tokens = $this->exchangeCode($user);

        $this->refresh('not-a-real-token')
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid_grant']);

        // An access token is not a refresh token
        $this->refresh($tokens['access_token'])
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid_grant']);

        $this->refresh('')->assertStatus(422);
    }

    public function test_expired_refresh_token_is_rejected()
    {
        $user = User::factory()->create();
        $tokens = $this->exchangeCode($user);

        $this->travel(31)->days();

        $this->refresh($tokens['refresh_token'])
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid_grant']);
    }

    public function test_revoked_refresh_token_is_rejected()
    {
        $user = User::factory()->create();
        $tokens = $this->exchangeCode($user);

        OAuthRefreshToken::query()->update(['revoked' => true]);

        $this->refresh($tokens['refresh_token'])
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid_grant']);

        $this->assertSame(1, OAuthAccessToken::count());
    }

    public function test_refresh_token_is_bound_to_its_client()
    {
        OAuthClient::create([
            'name' => 'Other Client',
            'client_id' => 'other_client',
            'client_secret' => 'other_secret',
            'redirect_uris' => ['http://localhost:4000/callback'],
            'confidential' => true,
            'active' => true,
        ]);
        $user = User::factory()->create();
        $tokens = $this->exchangeCode($user);

        $this->refresh($tokens['refresh_token'], ['client_id' => 'other_client', 'client_secret' => 'other_secret'])
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid_grant']);

        $this->assertFalse(OAuthRefreshToken::first()->revoked);
    }

    public function test_refresh_with_wrong_client_secret_is_rejected()
    {
        $user = User::factory()->create();
        $tokens = $this->exchangeCode($user);

        $this->refresh($tokens['refresh_token'], ['client_secret' => 'wrong'])
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_client']);

        $this->assertFalse(OAuthRefreshToken::first()->revoked);
    }
}
