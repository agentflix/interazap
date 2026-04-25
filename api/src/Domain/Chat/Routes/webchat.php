<?php

declare(strict_types=1);

/**
 * Rotas do Módulo Webchat.
 *
 * Endpoints públicos para visitantes webchat (sem autenticação Sanctum).
 */
use Domain\Chat\Http\Controllers\WebChatHealthController;
use Domain\Chat\Http\Controllers\WebChatCloseController;
use Domain\Chat\Http\Controllers\WebChatMediaController;
use Domain\Chat\Http\Controllers\WebChatMessageController;
use Domain\Chat\Http\Controllers\WebChatMessagesController;
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
    Route::get('/webchat/sessions/{id}/messages', [WebChatMessagesController::class, 'index']);

    // Message ingestion
    Route::post('/webchat/messages', [WebChatMessageController::class, 'store']);

    // Media upload
    Route::post('/webchat/media', [WebChatMediaController::class, 'store']);

    // Ticket closure
    Route::post('/webchat/close', [WebChatCloseController::class, 'store']);
});
