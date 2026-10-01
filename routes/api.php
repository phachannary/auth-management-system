<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\AppApiController;
use App\Http\Controllers\Api\RoleApiController;
use App\Http\Controllers\Api\PermissionApiController;
use App\Http\Controllers\OAuth\TokenController;
use App\Http\Controllers\OAuth\UserInfoController;
use App\Http\Controllers\OAuth\JwksController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * All routes below require a valid Cognito Access Token
 * in the Authorization: Bearer <token> header.
 *
 * Apps authenticate directly with Cognito, then send
 * the Cognito JWT to these endpoints.
 */

// OAuth 2.0 / OIDC Endpoints (no authentication required for token endpoint)
Route::prefix('oauth')->group(function () {
    // Token endpoint (authorization code grant, refresh token grant)
    Route::post('/token', [TokenController::class, 'token']);
    
    // UserInfo endpoint (requires OAuth access token)
    Route::get('/userinfo', [UserInfoController::class, 'userinfo']);
    
    // JWKS endpoint (public keys for JWT validation)
    Route::get('/.well-known/jwks.json', [JwksController::class, 'jwks']);
});

// OAuth 2.0 Protected API Routes (requires OAuth access token)
Route::middleware(['oauth.token'])->group(function () {
    Route::get('/oauth/test', function (Request $request) {
        return response()->json([
            'message' => 'OAuth token is valid!',
            'user_id' => $request->attributes->get('oauth_user_id'),
            'email' => $request->attributes->get('oauth_email'),
            'name' => $request->attributes->get('oauth_name'),
            'client_id' => $request->attributes->get('oauth_client_id'),
        ]);
    });
});

Route::middleware(['cognito.token'])->group(function () {

    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthApiController::class, 'me']);
        Route::post('/logout', [AuthApiController::class, 'logout']);
    });

    Route::prefix('apps')->group(function () {
        Route::get('/', [AppApiController::class, 'index']);
        Route::get('/{slug}', [AppApiController::class, 'show']);
        Route::get('/{slug}/cognito-clients', [AppApiController::class, 'cognitoClients']);
    });

    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleApiController::class, 'index']);
        Route::get('/{id}', [RoleApiController::class, 'show']);
        Route::post('/', [RoleApiController::class, 'store']);
        Route::post('/{id}/permissions', [RoleApiController::class, 'assignPermission']);
        Route::delete('/{id}/permissions', [RoleApiController::class, 'removePermission']);
    });

    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionApiController::class, 'index']);
        Route::get('/{id}', [PermissionApiController::class, 'show']);
        Route::post('/', [PermissionApiController::class, 'store']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/', [UserApiController::class, 'index']);
        Route::get('/{id}', [UserApiController::class, 'show']);
        Route::post('/{id}/roles', [UserApiController::class, 'assignRole']);
        Route::delete('/{id}/roles', [UserApiController::class, 'removeRole']);
    });
});
