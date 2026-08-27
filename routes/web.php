<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\CognitoLoginController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\FacebookController;

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

    // Registration (direct Cognito username/password signup)
    Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Email verification for direct signups
    Route::get('/verify', [AuthController::class, 'showVerificationForm'])->name('verify');
    Route::post('/verify', [AuthController::class, 'verify']);
    Route::post('/resend-verify', [AuthController::class, 'resendVerificationCode'])->name('resend-verify');

    // Cognito Hosted UI (SSO: handles Google, Facebook, and Cognito login)
    Route::get('/cognito', [CognitoLoginController::class, 'redirectToCognito'])->name('cognito');
    Route::get('/cognito/callback', [CognitoLoginController::class, 'handleCognitoCallback'])->name('cognito.callback');

    // Socialite OAuth (original working flow)
    Route::get('/google', [GoogleController::class, 'redirectToGoogle'])->name('google');
    Route::get('/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('google.callback');
    Route::get('/facebook', [FacebookController::class, 'redirectToFacebook'])->name('facebook');
    Route::get('/facebook/callback', [FacebookController::class, 'handleFacebookCallback'])->name('facebook.callback');

    // Logout (clears local + Cognito session)
    Route::post('/logout', [CognitoLoginController::class, 'logout'])->name('logout');
    Route::get('/logout', [CognitoLoginController::class, 'logout'])->name('logout.get');
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
