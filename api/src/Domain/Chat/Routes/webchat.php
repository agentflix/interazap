<?php

declare(strict_types=1);

/**
 * Rotas do Módulo Webchat.
 *
 * Endpoints públicos para visitantes webchat (sem autenticação Sanctum).
 */
use Domain\Chat\Http\Controllers\WebChatHealthController;
use Domain\Chat\Http\Controllers\WebChatMessageController;
use Domain\Chat\Http\Controllers\WebChatSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webchat Public Routes (no auth)
|--------------------------------------------------------------------------
*/
Route::middleware(['throttle:webchat'])->group(function (): void {
    // Health check
    Route::get('/webchat/health', [WebChatHealthController::class, '__invoke']);

    // Session management
    Route::post('/webchat/sessions', [WebChatSessionController::class, 'store']);
    Route::get('/webchat/sessions/{id}', [WebChatSessionController::class, 'show']);

    // Message ingestion
    Route::post('/webchat/messages', [WebChatMessageController::class, 'store']);
});
