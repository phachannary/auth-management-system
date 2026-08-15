<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\AppApiController;
use App\Http\Controllers\Api\RoleApiController;
use App\Http\Controllers\Api\PermissionApiController;
use Illuminate\Support\Facades\Route;

/*
 * All routes below require a valid Cognito Access Token
 * in the Authorization: Bearer <token> header.
 *
 * Apps authenticate directly with Cognito, then send
 * the Cognito JWT to these endpoints.
 */
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
