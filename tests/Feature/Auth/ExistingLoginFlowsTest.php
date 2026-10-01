<?php

namespace Tests\Feature\Auth;

use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\User;
use App\Services\CognitoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Regression tests for the existing login flows (password via Cognito, Google,
 * Facebook, Cognito Hosted UI), with and without a pending OAuth request.
 *
 * Cognito and Socialite are mocked: these tests exercise this app's controller
 * logic, not the live AWS / Google / Facebook integrations.
 */
class ExistingLoginFlowsTest extends TestCase
{
    use RefreshDatabase;

    private const REDIRECT_URI = 'http://localhost:3000/auth/callback';

    protected function setUp(): void
    {
        parent::setUp();

        OAuthClient::create([
            'name' => 'DS1 Test Client',
            'client_id' => 'ds1_test_client',
            'client_secret' => 'test_secret',
            'redirect_uris' => [self::REDIRECT_URI],
            'confidential' => true,
            'active' => true,
        ]);
    }

    // --- Password login (Cognito InitiateAuth) ----------------------------

    public function test_password_login_without_oauth_request_goes_to_dashboard()
    {
        $user = User::factory()->create(['name' => 'alice']);
        $this->mockSuccessfulCognitoPasswordLogin();

        $this->post('/auth/login', ['username' => 'alice', 'password' => 'secret'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('cognito-sub-alice', $user->fresh()->cognito_sub);
    }

    public function test_password_login_with_oauth_request_returns_to_client_with_code()
    {
        $user = User::factory()->create(['name' => 'alice']);
        $this->mockSuccessfulCognitoPasswordLogin();

        $this->get($this->authorizeUrl())->assertRedirect(route('oauth.login'));

        $login = $this->post('/auth/login', ['username' => 'alice', 'password' => 'secret']);
        $login->assertStatus(302);
        $this->assertStringStartsWith(route('oauth.authorize') . '?', $login->headers->get('Location'));

        $this->assertCodeIssuedFor($user, $this->get($login->headers->get('Location')));
    }

    public function test_failed_password_login_does_not_authenticate()
    {
        $this->mock(CognitoService::class, function ($mock) {
            $mock->shouldReceive('initiateAuth')->andReturn(['success' => false, 'error' => 'Incorrect username or password.']);
        });

        $this->from('/auth/login')
            ->post('/auth/login', ['username' => 'alice', 'password' => 'wrong'])
            ->assertRedirect('/auth/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    // --- Google (Socialite) -----------------------------------------------

    public function test_returning_google_user_goes_to_dashboard()
    {
        $user = User::factory()->create(['google_id' => 'g-123']);
        $this->mockSocialiteUser('google', 'g-123', $user->email, $user->name);
        $this->mock(CognitoService::class);

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_returning_google_user_with_oauth_request_returns_to_client_with_code()
    {
        $user = User::factory()->create(['google_id' => 'g-123']);
        $this->mockSocialiteUser('google', 'g-123', $user->email, $user->name);
        $this->mock(CognitoService::class);

        $this->get($this->authorizeUrl())->assertRedirect(route('oauth.login'));

        $callback = $this->get('/auth/google/callback');
        $this->assertStringStartsWith(route('oauth.authorize') . '?', $callback->headers->get('Location'));

        $this->assertCodeIssuedFor($user, $this->get($callback->headers->get('Location')));
    }

    /**
     * This branch calls Session::regenerate(); before the missing import was
     * added it threw "Class App\Http\Controllers\Auth\Session not found".
     */
    public function test_google_user_confirmed_in_cognito_but_not_linked_locally_is_linked_and_logged_in()
    {
        $user = User::factory()->create(['email' => 'bob@gmail.com', 'google_id' => null]);
        $this->mockSocialiteUser('google', 'g-456', 'bob@gmail.com', 'Bob');
        $this->mock(CognitoService::class, function ($mock) {
            $mock->shouldReceive('getUserStatus')->with('bob')->andReturn(['success' => true, 'status' => 'CONFIRMED']);
        });

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-456', $user->fresh()->google_id);
    }

    public function test_new_google_user_is_sent_to_otp_verification()
    {
        $this->mockSocialiteUser('google', 'g-789', 'carol@gmail.com', 'Carol');
        $this->mock(CognitoService::class, function ($mock) {
            $mock->shouldReceive('getUserStatus')->andReturn(['success' => false, 'error' => 'UserNotFoundException']);
            $mock->shouldReceive('signUp')->andReturn(['success' => true, 'data' => []]);
        });

        $this->get('/auth/google/callback')
            ->assertRedirect(route('auth.verify'))
            ->assertSessionHas('verification_email', 'carol@gmail.com');

        $this->assertGuest();
    }

    // --- Facebook (Socialite) ---------------------------------------------

    /**
     * This branch calls Session::regenerate(); before the missing import was
     * added it threw "Class App\Http\Controllers\Auth\Session not found".
     */
    public function test_returning_facebook_user_goes_to_dashboard()
    {
        $user = User::factory()->create(['facebook_id' => 'fb-123']);
        $this->mockSocialiteUser('facebook', 'fb-123', $user->email, $user->name);
        $this->mock(CognitoService::class);

        $this->get('/auth/facebook/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_returning_facebook_user_with_oauth_request_returns_to_client_with_code()
    {
        $user = User::factory()->create(['facebook_id' => 'fb-123']);
        $this->mockSocialiteUser('facebook', 'fb-123', $user->email, $user->name);
        $this->mock(CognitoService::class);

        $this->get($this->authorizeUrl())->assertRedirect(route('oauth.login'));

        $callback = $this->get('/auth/facebook/callback');
        $this->assertStringStartsWith(route('oauth.authorize') . '?', $callback->headers->get('Location'));

        $this->assertCodeIssuedFor($user, $this->get($callback->headers->get('Location')));
    }

    // --- Cognito Hosted UI ------------------------------------------------

    public function test_cognito_hosted_ui_callback_logs_in_and_goes_to_dashboard()
    {
        $user = User::factory()->create(['email' => 'dave@example.com']);
        $this->mock(CognitoService::class, function ($mock) {
            $mock->shouldReceive('exchangeCodeForTokens')->andReturn(['success' => true, 'data' => [
                'id_token' => 'id-token',
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]]);
            $mock->shouldReceive('validateIdToken')->with('id-token')->andReturn(['success' => true, 'data' => [
                'sub' => 'cognito-sub-dave',
                'email' => 'dave@example.com',
                'cognito:username' => 'dave',
            ]]);
        });

        $this->get('/auth/cognito/callback?code=cognito-code')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('cognito-sub-dave', $user->fresh()->cognito_sub);
    }

    // --- New Google user + OTP verification with a pending OAuth request --

    public function test_new_google_user_completing_otp_verification_returns_to_client_with_code()
    {
        $this->mockSocialiteUser('google', 'g-789', 'carol@gmail.com', 'Carol');
        $this->mock(CognitoService::class, function ($mock) {
            $mock->shouldReceive('getUserStatus')->with('carol')->andReturn(['success' => true, 'status' => 'UNCONFIRMED']);
            $mock->shouldReceive('signUp')->andReturn(['success' => true, 'data' => []]);
            $mock->shouldReceive('confirmSignUp')->with('carol', '123456')->andReturn(['success' => true]);
        });

        $this->get($this->authorizeUrl())->assertRedirect(route('oauth.login'));
        $this->get('/auth/google/callback')->assertRedirect(route('auth.verify'));

        $verify = $this->post('/auth/verify', ['username' => 'carol', 'code' => '123456']);
        $this->assertStringStartsWith(route('oauth.authorize') . '?', $verify->headers->get('Location'));

        $user = User::where('email', 'carol@gmail.com')->firstOrFail();
        $this->assertCodeIssuedFor($user, $this->get($verify->headers->get('Location')));
    }

    // --- Stale / abandoned OAuth requests ---------------------------------

    public function test_authorize_stores_oauth_request_with_expiry()
    {
        $this->freezeSecond();

        $this->get($this->authorizeUrl());

        $expiresAt = session('oauth_request')['expires_at'];
        $this->assertSame(now()->addMinutes(15)->timestamp, $expiresAt);
    }

    public function test_oauth_request_within_ttl_still_redirects_after_password_login()
    {
        User::factory()->create(['name' => 'alice']);
        $this->mockSuccessfulCognitoPasswordLogin();

        $this->get($this->authorizeUrl());
        $this->travel(14)->minutes();

        $login = $this->post('/auth/login', ['username' => 'alice', 'password' => 'secret']);
        $this->assertStringStartsWith(route('oauth.authorize') . '?', $login->headers->get('Location'));
    }

    public function test_expired_oauth_request_does_not_affect_later_password_login()
    {
        $user = User::factory()->create(['name' => 'alice']);
        $this->mockSuccessfulCognitoPasswordLogin();

        $this->get($this->authorizeUrl());
        $this->travel(16)->minutes();

        $this->post('/auth/login', ['username' => 'alice', 'password' => 'secret'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('oauth_request');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, OAuthAuthCode::count());
    }

    public function test_expired_oauth_request_does_not_affect_later_google_login()
    {
        $user = User::factory()->create(['google_id' => 'g-123']);
        $this->mockSocialiteUser('google', 'g-123', $user->email, $user->name);
        $this->mock(CognitoService::class);

        $this->get($this->authorizeUrl());
        $this->travel(16)->minutes();

        $this->get('/auth/google/callback')
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('oauth_request');
    }

    public function test_expired_oauth_request_does_not_affect_later_facebook_login()
    {
        $user = User::factory()->create(['facebook_id' => 'fb-123']);
        $this->mockSocialiteUser('facebook', 'fb-123', $user->email, $user->name);
        $this->mock(CognitoService::class);

        $this->get($this->authorizeUrl());
        $this->travel(16)->minutes();

        $this->get('/auth/facebook/callback')
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('oauth_request');
    }

    public function test_oauth_request_without_expiry_is_treated_as_stale()
    {
        // Shape stored by the previous implementation (no expires_at)
        $user = User::factory()->create(['name' => 'alice']);
        $this->mockSuccessfulCognitoPasswordLogin();

        $this->withSession(['oauth_request' => [
            'client_id' => 'ds1_test_client',
            'redirect_uri' => self::REDIRECT_URI,
            'scope' => 'openid profile email',
            'state' => 'old-state',
        ]])
            ->post('/auth/login', ['username' => 'alice', 'password' => 'secret'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('oauth_request');

        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_oauth_request_on_oauth_login_page_falls_back_to_normal_login()
    {
        $this->get($this->authorizeUrl());
        $this->travel(16)->minutes();

        $this->get(route('oauth.login'))->assertRedirect(route('auth.login'));
    }

    public function test_restarting_authorization_after_expiry_works()
    {
        User::factory()->create(['name' => 'alice']);
        $this->mockSuccessfulCognitoPasswordLogin();

        $this->get($this->authorizeUrl());
        $this->travel(16)->minutes();
        $this->get($this->authorizeUrl())->assertRedirect(route('oauth.login'));

        $login = $this->post('/auth/login', ['username' => 'alice', 'password' => 'secret']);
        $this->assertStringStartsWith(route('oauth.authorize') . '?', $login->headers->get('Location'));
    }

    // --- helpers ----------------------------------------------------------

    private function authorizeUrl(): string
    {
        return '/oauth/authorize?' . http_build_query([
            'client_id' => 'ds1_test_client',
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'state' => 'state-xyz',
        ]);
    }

    private function assertCodeIssuedFor(User $user, $authorizeResponse): void
    {
        $authorizeResponse->assertStatus(302);
        $location = $authorizeResponse->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT_URI . '?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('state-xyz', $query['state']);
        $this->assertSame(
            $user->id,
            (int) OAuthAuthCode::where('code', hash('sha256', $query['code']))->value('user_id')
        );
    }

    private function mockSuccessfulCognitoPasswordLogin(): void
    {
        $this->mock(CognitoService::class, function ($mock) {
            $mock->shouldReceive('initiateAuth')->with('alice', 'secret')->andReturn(['success' => true, 'data' => [
                'AuthenticationResult' => [
                    'AccessToken' => 'access-token',
                    'RefreshToken' => 'refresh-token',
                    'IdToken' => 'id-token',
                    'ExpiresIn' => 3600,
                    'TokenType' => 'Bearer',
                ],
            ]]);
            $mock->shouldReceive('getUser')->with('access-token')->andReturn(['success' => true, 'data' => ['Username' => 'alice']]);
            $mock->shouldReceive('validateIdToken')->with('id-token')->andReturn(['success' => true, 'data' => ['sub' => 'cognito-sub-alice']]);
        });
    }

    private function mockSocialiteUser(string $driver, string $id, string $email, string $name): void
    {
        $socialUser = (new SocialiteUser())->map(['id' => $id, 'email' => $email, 'name' => $name]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with($driver)->andReturn($provider);
    }
}
