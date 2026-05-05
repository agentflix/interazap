<?php

declare(strict_types=1);

use Domain\Auth\Http\Controllers\AuthLoginController;
use Domain\Auth\Http\Controllers\AuthPasswordResetController;
use Domain\Auth\Http\Controllers\AuthProfileController;
use Domain\Auth\Http\Controllers\AuthRoleController;
use Domain\Auth\Http\Controllers\AuthTwoFactorController;
use Domain\Auth\Http\Controllers\AuthUserController;
use Domain\Auth\Http\Controllers\DeviceTokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Auth Routes (SEC-001: Strict Rate Limiting)
|--------------------------------------------------------------------------
| Login and password reset endpoints with aggressive rate limiting
| to prevent brute force attacks.
*/
Route::prefix('auth')
    ->middleware(['throttle:login']) // Dedicated login limiter (5 req/min)
    ->group(function (): void {
        Route::post('/login', [AuthLoginController::class, 'login']);
        Route::post('/login-with-2fa', [AuthLoginController::class, 'loginWith2FA']);
        Route::post('/forgot-password', [AuthPasswordResetController::class, 'forgot']);
        Route::post('/reset-password', [AuthPasswordResetController::class, 'reset']);
    });

Route::middleware(['auth:sanctum'])
    ->prefix('auth')
    ->group(function (): void {
        Route::get('/me', [AuthLoginController::class, 'me']);
        Route::post('/logout', [AuthLoginController::class, 'logout']);
        Route::get('/get-menu', [AuthLoginController::class, 'getMenu']);
        Route::post('/refresh', [AuthLoginController::class, 'refresh']);
        Route::post('/stop-impersonating', [AuthLoginController::class, 'stopImpersonating']);

        // Perfil
        Route::get('/profile', [AuthProfileController::class, 'show']);
        Route::put('/profile', [AuthProfileController::class, 'update']);
        Route::put('/profile/password', [AuthProfileController::class, 'updatePassword']);
        Route::post('/profile/avatar', [AuthProfileController::class, 'uploadAvatar']);
        Route::delete('/profile/avatar', [AuthProfileController::class, 'deleteAvatar']);
        Route::get('/profile/preferences', [AuthProfileController::class, 'preferences']);
        Route::patch('/profile/preferences', [AuthProfileController::class, 'updatePreferences']);

        // 2FA
        Route::get('/2fa/status', [AuthTwoFactorController::class, 'status']);
        Route::post('/2fa/setup', [AuthTwoFactorController::class, 'setup']);
        Route::post('/2fa/validate', [AuthTwoFactorController::class, 'validateSetup']);
        Route::post('/2fa/disable', [AuthTwoFactorController::class, 'disable']);
        Route::post('/2fa/recovery-codes', [AuthTwoFactorController::class, 'regenerate']);

        // Roles
        Route::get('/roles', [AuthRoleController::class, 'index']);
        Route::post('/roles', [AuthRoleController::class, 'store']);
        Route::get('/roles/permissions', [AuthRoleController::class, 'permissions']);
        Route::get('/roles/{id}', [AuthRoleController::class, 'show']);
        Route::put('/roles/{id}', [AuthRoleController::class, 'update']);
        Route::delete('/roles/{id}', [AuthRoleController::class, 'destroy']);

        // Usuários
        Route::get('/users', [AuthUserController::class, 'index']);
        Route::post('/users', [AuthUserController::class, 'store']);
        Route::get('/users/{id}', [AuthUserController::class, 'show']);
        Route::put('/users/{id}', [AuthUserController::class, 'update']);
        Route::delete('/users/{id}', [AuthUserController::class, 'destroy']);
        Route::post('/users/{id}/toggle', [AuthUserController::class, 'toggle']);
        Route::post('/users/{id}/avatar', [AuthUserController::class, 'uploadAvatar']);
        Route::delete('/users/{id}/avatar', [AuthUserController::class, 'deleteAvatar']);
    });

Route::middleware(['auth:sanctum'])
    ->group(function (): void {
        Route::post('/devices/register', [DeviceTokenController::class, 'register']);
        Route::delete('/devices/{id}', [DeviceTokenController::class, 'destroy']);
    });
