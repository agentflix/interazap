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

// Public signup — rate-limited at 10 req/min/IP
Route::prefix('auth')
    ->middleware(['throttle:public'])
    ->group(function (): void {
        Route::post('/signup', [\Domain\Auth\Http\Controllers\AuthSignupController::class, 'store']);

        // Google OAuth — public (login/signup)
        Route::get('/google/redirect', [\Domain\Auth\Http\Controllers\AuthGoogleController::class, 'redirect']);
        Route::get('/google/callback', [\Domain\Auth\Http\Controllers\AuthGoogleController::class, 'callback'])
            ->middleware(['throttle:login']);
    });

// Google OAuth — link provider to authenticated user
Route::prefix('auth')
    ->middleware(['auth:sanctum', 'throttle:public'])
    ->group(function (): void {
        Route::get('/google/link', [\Domain\Auth\Http\Controllers\AuthGoogleController::class, 'redirectForLink']);
    });

Route::middleware(['auth:sanctum'])
    ->prefix('auth')
    ->group(function (): void {
        // Rotas permitidas mesmo com force_password_change ativo
        Route::get('/me', [AuthLoginController::class, 'me']);
        Route::post('/logout', [AuthLoginController::class, 'logout']);
        Route::put('/force-password-change', [AuthProfileController::class, 'forcePasswordChange']);

        // Demais rotas protegidas pelo middleware de troca obrigatória de senha
        Route::middleware(['force.password.change'])->group(function (): void {
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
            Route::get('/roles/{id}/users', [AuthRoleController::class, 'users']);

            // Usuários
            Route::get('/users', [AuthUserController::class, 'index']);
            Route::post('/users', [AuthUserController::class, 'store']);
            Route::get('/users/{id}', [AuthUserController::class, 'show']);
            Route::put('/users/{id}', [AuthUserController::class, 'update']);
            Route::delete('/users/{id}', [AuthUserController::class, 'destroy']);
            Route::post('/users/{id}/toggle', [AuthUserController::class, 'toggle']);
            Route::post('/users/{id}/roles/remove', [AuthUserController::class, 'removeRole']);
            Route::post('/users/{id}/avatar', [AuthUserController::class, 'uploadAvatar']);
            Route::delete('/users/{id}/avatar', [AuthUserController::class, 'deleteAvatar']);
        });
    });

Route::middleware(['auth:sanctum'])
    ->group(function (): void {
        Route::post('/devices/register', [DeviceTokenController::class, 'register']);
        Route::delete('/devices/{id}', [DeviceTokenController::class, 'destroy']);
    });
