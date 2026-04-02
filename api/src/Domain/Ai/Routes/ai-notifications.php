<?php

declare(strict_types=1);

use Domain\Ai\Http\Controllers\AiNotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Notification Routes
|--------------------------------------------------------------------------
|
| Routes for AI seller notifications.
|
*/

Route::prefix('ai/notifications')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/', [AiNotificationController::class, 'index'])->name('ai.notifications.index');
    Route::get('/unread-count', [AiNotificationController::class, 'unreadCount'])->name('ai.notifications.unread-count');
    Route::get('/{id}', [AiNotificationController::class, 'show'])->name('ai.notifications.show');
    Route::post('/mark-read', [AiNotificationController::class, 'markAsRead'])->name('ai.notifications.mark-read');
});
