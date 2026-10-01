<?php

namespace Tests\Feature\OAuth;

use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthorizationCodeFlowTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'ds1_test_client';
    private const CLIENT_SECRET = 'test_secret';
    private const REDIRECT_URI = 'http://localhost:3000/auth/callback';

    protected function setUp(): void
    {
        parent::setUp();

        OAuthClient::create([
            'name' => 'DS1 Test Client',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
            'redirect_uris' => [self::REDIRECT_URI],
            'scopes' => ['openid', 'profile', 'email'],
            'confidential' => true,
            'active' => true,
        ]);
    }

    // --- /oauth/authorize -------------------------------------------------

    public function test_logged_out_authorize_stores_request_and_shows_oauth_login()
    {
        $this->freezeSecond();

        $response = $this->get($this->authorizeUrl());

        $response->assertRedirect(route('oauth.login'));
        $response->assertSessionHas('oauth_request', [
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => self::REDIRECT_URI,
            'scope' => 'openid profile email',
            'state' => 'state-123',
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);
        $this->assertSame(0, OAuthAuthCode::count());

        $this->get(route('oauth.login'))
            ->assertOk()
            ->assertViewIs('oauth.login');
    }

    public function test_logged_in_authorize_redirects_to_client_with_code_and_state()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get($this->authorizeUrl());

        $response->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT_URI . '?', $location);

        $query = $this->queryFrom($response);
        $this->assertSame('state-123', $query['state']);
        $this->assertSame(64, strlen($query['code']));
        $response->assertSessionMissing('oauth_request');
    }

    public function test_authorization_code_is_stored_hashed_and_bound_to_client_and_redirect_uri()
    {
        $user = User::factory()->create();

        $code = $this->issueCode($user);

        $this->assertSame(1, OAuthAuthCode::count());
        $record = OAuthAuthCode::first();
        $this->assertSame(hash('sha256', $code), $record->code);
        $this->assertNotSame($code, $record->code);
        $this->assertSame($user->id, (int) $record->user_id);
        $this->assertSame(self::CLIENT_ID, $record->client_id);
        $this->assertSame(self::REDIRECT_URI, $record->redirect_uri);
        $this->assertSame(['openid', 'profile', 'email'], $record->scopes);
        $this->assertTrue($record->expires_at->isFuture());
        $this->assertTrue($record->expires_at->lte(now()->addMinutes(10)));
    }

    public function test_invalid_client_id_shows_error_page_without_redirecting()
    {
        $response = $this->get($this->authorizeUrl([
            'client_id' => 'unknown_client',
            'redirect_uri' => 'https://evil.example.com/steal',
        ]));

        $response->assertStatus(400);
        $response->assertViewIs('oauth.error');
        $response->assertSee('invalid_client');
        $response->assertHeaderMissing('Location');
    }

    public function test_inactive_client_shows_error_page_without_redirecting()
    {
        OAuthClient::where('client_id', self::CLIENT_ID)->update(['active' => false]);

        $response = $this->get($this->authorizeUrl());

        $response->assertStatus(400);
        $response->assertHeaderMissing('Location');
    }

    public function test_missing_client_id_shows_error_page_without_redirecting()
    {
        $response = $this->get('/oauth/authorize?' . http_build_query([
            'redirect_uri' => 'https://evil.example.com/steal',
            'response_type' => 'code',
        ]));

        $response->assertStatus(400);
        $response->assertHeaderMissing('Location');
    }

    public function test_unregistered_redirect_uri_shows_error_page_without_redirecting()
    {
        $response = $this->get($this->authorizeUrl([
            'redirect_uri' => 'https://evil.example.com/steal',
        ]));

        $response->assertStatus(400);
        $response->assertViewIs('oauth.error');
        $response->assertHeaderMissing('Location');
    }

    public function test_lookalike_redirect_uri_with_registered_prefix_is_rejected()
    {
        foreach ([
            self::REDIRECT_URI . '.evil.com',
            self::REDIRECT_URI . '/../../evil',
            self::REDIRECT_URI . '?next=https://evil.example.com',
            'http://localhost:3000/auth/callback/',
        ] as $uri) {
            $response = $this->get($this->authorizeUrl(['redirect_uri' => $uri]));

            $response->assertStatus(400);
            $response->assertHeaderMissing('Location');
        }
    }

    public function test_redirect_uri_errors_never_issue_a_code_for_logged_in_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get($this->authorizeUrl(['redirect_uri' => 'https://evil.example.com/steal']))
            ->assertStatus(400);

        $this->assertSame(0, OAuthAuthCode::count());
    }

    public function test_unsupported_response_type_is_returned_to_the_validated_redirect_uri()
    {
        $response = $this->get($this->authorizeUrl(['response_type' => 'token']));

        $response->assertStatus(302);
        $this->assertStringStartsWith(self::REDIRECT_URI . '?', $response->headers->get('Location'));
        $query = $this->queryFrom($response);
        $this->assertSame('unsupported_response_type', $query['error']);
        $this->assertSame('state-123', $query['state']);
    }

    // --- /api/oauth/token -------------------------------------------------

    public function test_token_exchange_returns_tokens_and_consumes_code()
    {
        $user = User::factory()->create();
        $code = $this->issueCode($user);

        $response = $this->postJson('/api/oauth/token', $this->tokenRequest($code));

        $response->assertOk();
        $response->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'refresh_token', 'id_token']);
        $response->assertJson(['token_type' => 'Bearer', 'expires_in' => 3600]);

        $this->assertSame(0, OAuthAuthCode::count());
        $this->assertSame(1, OAuthAccessToken::count());
        $this->assertSame(1, OAuthRefreshToken::count());
        $this->assertSame(hash('sha256', $response->json('access_token')), OAuthAccessToken::first()->token);
        $this->assertSame(OAuthAccessToken::first()->id, OAuthRefreshToken::first()->accessToken->id);
    }

    public function test_authorization_code_replay_is_rejected()
    {
        $user = User::factory()->create();
        $code = $this->issueCode($user);

        $this->postJson('/api/oauth/token', $this->tokenRequest($code))->assertOk();

        $this->postJson('/api/oauth/token', $this->tokenRequest($code))
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid_grant']);

        $this->assertSame(1, OAuthAccessToken::count());
    }

    public function test_token_exchange_with_wrong_redirect_uri_is_rejected()
    {
        $user = User::factory()->create();
        $code = $this->issueCode($user);

        $this->postJson('/api/oauth/token', $this->tokenRequest($code, [
            'redirect_uri' => 'http://localhost:8000/auth/callback',
        ]))
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid_grant']);

        $this->assertSame(0, OAuthAccessToken::count());
    }

    public function test_token_exchange_with_wrong_client_secret_is_rejected()
    {
        $user = User::factory()->create();
        $code = $this->issueCode($user);

        $this->postJson('/api/oauth/token', $this->tokenRequest($code, ['client_secret' => 'wrong']))
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_client']);

        $this->postJson('/api/oauth/token', $this->tokenRequest($code, ['client_secret' => null]))
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_client']);

        $this->assertSame(0, OAuthAccessToken::count());
        $this->assertSame(1, OAuthAuthCode::count());
    }

    public function test_token_exchange_with_invalid_client_id_is_rejected()
    {
        $user = User::factory()->create();
        $code = $this->issueCode($user);

        $this->postJson('/api/oauth/token', $this->tokenRequest($code, ['client_id' => 'unknown_client']))
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_client']);
    }

    public function test_expired_authorization_code_is_rejected()
    {
        $user = User::factory()->create();
        $code = $this->issueCode($user);

        $this->travel(11)->minutes();

        $this->postJson('/api/oauth/token', $this->tokenRequest($code))
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid_grant']);
    }

    // --- /api/oauth/userinfo ----------------------------------------------

    public function test_userinfo_returns_claims_for_valid_access_token()
    {
        $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $tokens = $this->exchange($user);

        $this->withToken($tokens['access_token'])
            ->getJson('/api/oauth/userinfo')
            ->assertOk()
            ->assertExactJson([
                'sub' => (string) $user->id,
                'email' => 'jane@example.com',
                'email_verified' => true,
                'name' => 'Jane Doe',
                'given_name' => 'Jane',
                'family_name' => 'Doe',
            ]);
    }

    public function test_userinfo_rejects_missing_invalid_and_non_access_tokens()
    {
        $user = User::factory()->create();
        $tokens = $this->exchange($user);

        $this->getJson('/api/oauth/userinfo')->assertStatus(401);
        $this->withToken('not-a-jwt')->getJson('/api/oauth/userinfo')->assertStatus(401);
        $this->withToken($tokens['refresh_token'])->getJson('/api/oauth/userinfo')->assertStatus(401);
        $this->withToken($tokens['id_token'])->getJson('/api/oauth/userinfo')->assertStatus(401);
    }

    public function test_userinfo_rejects_revoked_access_token()
    {
        $user = User::factory()->create();
        $tokens = $this->exchange($user);

        OAuthAccessToken::query()->update(['revoked' => true]);

        $this->withToken($tokens['access_token'])->getJson('/api/oauth/userinfo')->assertStatus(401);
    }

    // --- /api/oauth/.well-known/jwks.json --------------------------------

    public function test_jwks_does_not_expose_the_signing_secret()
    {
        // Ensure the key exists so we can compare against it
        app(\App\Services\JwtService::class);
        $secret = file_get_contents(storage_path('app/oauth_secret.key'));
        $secretB64Url = rtrim(strtr(base64_encode($secret), '+/', '-_'), '=');

        $response = $this->getJson('/api/oauth/.well-known/jwks.json');

        $response->assertOk();
        $response->assertExactJson(['keys' => []]);
        $this->assertStringNotContainsString($secret, $response->getContent());
        $this->assertStringNotContainsString($secretB64Url, $response->getContent());
    }

    // --- helpers ----------------------------------------------------------

    private function authorizeUrl(array $overrides = []): string
    {
        return '/oauth/authorize?' . http_build_query(array_merge([
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => 'state-123',
        ], $overrides));
    }

    private function issueCode(User $user): string
    {
        $response = $this->actingAs($user)->get($this->authorizeUrl());
        $response->assertStatus(302);

        return $this->queryFrom($response)['code'];
    }

    private function exchange(User $user): array
    {
        $response = $this->postJson('/api/oauth/token', $this->tokenRequest($this->issueCode($user)));
        $response->assertOk();

        return $response->json();
    }

    private function tokenRequest(string $code, array $overrides = []): array
    {
        return array_merge([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::REDIRECT_URI,
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
        ], $overrides);
    }

    private function queryFrom(TestResponse $response): array
    {
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query;
    }
}
