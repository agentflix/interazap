<?php

declare(strict_types=1);

use Domain\Ai\Http\Controllers\AiUsageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Usage Routes
|--------------------------------------------------------------------------
|
| Routes for AI usage analytics and metrics.
|
*/

Route::prefix('ai/usage')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/summary', [AiUsageController::class, 'summary'])->name('ai.usage.summary');
    Route::get('/daily', [AiUsageController::class, 'daily'])->name('ai.usage.daily');
    Route::get('/top-agents', [AiUsageController::class, 'topAgents'])->name('ai.usage.top-agents');
    Route::get('/monthly', [AiUsageController::class, 'monthlyHistory'])->name('ai.usage.monthly');
    Route::get('/transcription', [AiUsageController::class, 'transcriptionReport'])->name('ai.usage.transcription');
});
