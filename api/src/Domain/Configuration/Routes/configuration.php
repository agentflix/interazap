<?php

/**
 * Rotas do domínio Configuration.
 *
 * Define endpoints para notificações, preferências, horários de funcionamento,
 * transcrição de mídia e configurações de agendamento, todos protegidos por autenticação Sanctum.
 */

declare(strict_types=1);

use Domain\Configuration\Http\Controllers\ConfigurationMediaTranscriptionController;
use Domain\Configuration\Http\Controllers\ConfigurationNotificationController;
use Domain\Configuration\Http\Controllers\ConfigurationOpeningHourController;
use Domain\Configuration\Http\Controllers\ConfigurationSchedulingSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::prefix('notifications')->group(function (): void {
        Route::get('/', [ConfigurationNotificationController::class, 'index']);
        Route::patch('{id}/read', [ConfigurationNotificationController::class, 'markAsRead']);
        Route::post('read-all', [ConfigurationNotificationController::class, 'markAllAsRead']);
        Route::get('preferences', [ConfigurationNotificationController::class, 'preferences']);
        Route::put('preferences/{type}', [ConfigurationNotificationController::class, 'updatePreference']);
        Route::put('preferences', [ConfigurationNotificationController::class, 'updateAllPreferences']);
        Route::post('push-subscribe', [ConfigurationNotificationController::class, 'pushSubscribe']);
        Route::delete('push-subscribe', [ConfigurationNotificationController::class, 'pushUnsubscribe']);
    });

    Route::prefix('opening-hours')->group(function (): void {
        Route::get('/', [ConfigurationOpeningHourController::class, 'index']);
        Route::post('/', [ConfigurationOpeningHourController::class, 'store']);
        Route::put('bulk', [ConfigurationOpeningHourController::class, 'bulk']);
        Route::get('is-open', [ConfigurationOpeningHourController::class, 'isOpen']);
        Route::get('{id}', [ConfigurationOpeningHourController::class, 'show']);
        Route::put('{id}', [ConfigurationOpeningHourController::class, 'update']);
        Route::delete('{id}', [ConfigurationOpeningHourController::class, 'destroy']);
    });

    Route::prefix('media-transcription')->group(function (): void {
        Route::get('/', [ConfigurationMediaTranscriptionController::class, 'show']);
        Route::put('/', [ConfigurationMediaTranscriptionController::class, 'update']);
    });

    Route::prefix('scheduling')->group(function (): void {
        Route::get('/', [ConfigurationSchedulingSettingController::class, 'index']);
        Route::put('/', [ConfigurationSchedulingSettingController::class, 'update']);
    });
});
