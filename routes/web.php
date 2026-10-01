<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\CognitoLoginController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\FacebookController;
use App\Http\Controllers\OAuth\AuthorizationController;
use App\Http\Controllers\OAuth\OAuthLoginController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('home');
})->name('home');

// Legal Pages
Route::get('/privacy', function () {
    return view('privacy');
})->name('privacy');


// Authentication Routes
Route::prefix('auth')->name('auth.')->group(function () {
    // Login form (shows username/password + "Login with Cognito" button)
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/verify', [AuthController::class, 'showVerificationForm'])->name('verify');
    Route::post('/verify', [AuthController::class, 'verify']);
    Route::post('/resend-code', [AuthController::class, 'resendVerificationCode'])->name('resend-verify');
    Route::get('/clear-session', function () {
        Session::forget(['username', 'verification_username', 'verification_email', 'verification_expires_at', 'google_oauth_email', 'google_oauth_name', 'google_oauth_id']);
        return redirect()->route('auth.login')->with('success', 'Session cleared. Please login.');
    })->name('clear-session');

    // Socialite OAuth (working implementations)
    Route::get('/google', [GoogleController::class, 'redirectToGoogle'])->name('google');
    Route::get('/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('google.callback');
    Route::get('/facebook', [FacebookController::class, 'redirectToFacebook'])->name('facebook');
    Route::get('/facebook/callback', [FacebookController::class, 'handleFacebookCallback'])->name('facebook.callback');

    // Cognito Hosted UI (SSO: handles Google, Facebook, and Cognito login)
    Route::get('/cognito', [CognitoLoginController::class, 'redirectToCognito'])->name('cognito');
    Route::get('/cognito/callback', [CognitoLoginController::class, 'handleCognitoCallback'])->name('cognito.callback');

    // Logout (clears local + Cognito session)
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout.get');
});

// OAuth 2.0 / OIDC Routes
Route::prefix('oauth')->name('oauth.')->group(function () {
    // Authorization endpoint
    Route::get('/authorize', [AuthorizationController::class, 'handleAuthorize'])->name('authorize');
    
    // OAuth login page (shown when user is not logged in)
    Route::get('/login', [OAuthLoginController::class, 'showLoginForm'])->name('login');
});

// Protected Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    Route::post('/refresh-tokens', [AuthController::class, 'refreshTokens'])->name('refresh.tokens');

    // DEV ONLY: Get Sanctum token for API testing in Postman
    Route::get('/dev/api-token', function () {
        $user = auth()->user();
        $token = $user->createToken('postman-test')->plainTextToken;
        return response()->json([
            'token' => $token,
            'usage' => 'Authorization: Bearer ' . $token,
            'user' => ['id' => $user->id, 'email' => $user->email],
        ]);
    })->name('dev.token');
});
