<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\FacebookController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\CognitoLoginController;

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


// Authentication Routes
Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/verify', [AuthController::class, 'showVerificationForm'])->name('verify');
    Route::post('/verify', [AuthController::class, 'verify']);
    Route::post('/resend-verify', [AuthController::class, 'resendVerificationCode'])->name('resend-verify');
    Route::get('/cognito', [AuthController::class, 'redirectToCognito'])->name('cognito');
    Route::get('/callback', [AuthController::class, 'handleCognitoCallback'])->name('callback');

    // Debug route to test Cognito
    Route::get('/debug-cognito', function() {
        $url = app(\App\Services\CognitoService::class)->getHostedUIUrl();
        return "Cognito URL: " . $url;
    });

    // Google OAuth Routes
    Route::get('/google', [GoogleController::class, 'redirectToGoogle'])->name('google');
    Route::get('/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('google.callback');

    // Email Verification Routes
    Route::get('/verify-email', [EmailVerificationController::class, 'showVerificationForm'])->name('verification.form');
    Route::post('/verify-email', [EmailVerificationController::class, 'verify'])->name('verification.verify');
    Route::post('/resend-verification', [EmailVerificationController::class, 'resendCode'])->name('verification.resend');

    // Cognito Hosted UI Routes
    Route::get('/cognito', [CognitoLoginController::class, 'redirectToCognito'])->name('cognito');
    Route::get('/cognito/callback', [CognitoLoginController::class, 'handleCognitoCallback'])->name('cognito.callback');

    // AWS Login Route
    Route::post('/aws-login', [AuthController::class, 'awsLogin'])->name('auth.aws.login');

    // Facebook OAuth Routes
    Route::get('/facebook', [FacebookController::class, 'redirectToFacebook'])->name('facebook');
    Route::get('/facebook/callback', [FacebookController::class, 'handleFacebookCallback'])->name('facebook.callback');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout.get');
});

// Protected Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    Route::post('/refresh-tokens', [AuthController::class, 'refreshTokens'])->name('refresh.tokens');
});

// Standard Auth Routes (for Google login)
Route::middleware('auth')->group(function () {
    Route::get('/home', function() {
        return view('dashboard', ['user' => Auth::user()]);
    })->name('home');
});
