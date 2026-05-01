<?php

declare(strict_types=1);

use Domain\Ai\Http\Controllers\AiKnowledgeController;
use Illuminate\Support\Facades\Route;

/**
 * AI Knowledge Base (RAG) Routes
 *
 * Prefix: /api/ai/knowledge
 * Middleware: auth:sanctum
 */
Route::middleware(['auth:sanctum'])->prefix('ai/knowledge')->group(function (): void {
    // List documents
    Route::get('/', [AiKnowledgeController::class, 'index'])->name('ai.knowledge.index');

    // Upload document
    Route::post('/upload', [AiKnowledgeController::class, 'store'])->name('ai.knowledge.store');

    // Get statistics
    Route::get('/stats', [AiKnowledgeController::class, 'stats'])->name('ai.knowledge.stats');

    // Get RAG query statistics
    Route::get('/rag-stats', [AiKnowledgeController::class, 'ragStats'])
        ->middleware('throttle:30')
        ->name('ai.knowledge.rag-stats');

    // Search (POST for longer queries)
    Route::post('/search', [AiKnowledgeController::class, 'search'])->name('ai.knowledge.search');
    Route::post('/url', [AiKnowledgeController::class, 'ingestUrl'])->name('ai.knowledge.url.store');
    Route::delete('/bulk', [AiKnowledgeController::class, 'bulkDelete'])
        ->middleware('throttle:ai-knowledge-bulk')
        ->name('ai.knowledge.bulk.delete');
    Route::post('/bulk-reindex', [AiKnowledgeController::class, 'bulkReindex'])
        ->middleware('throttle:ai-knowledge-bulk')
        ->name('ai.knowledge.bulk.reindex');
    Route::get('/{id}/chunks', [AiKnowledgeController::class, 'chunks'])->name('ai.knowledge.chunks');

    // Document-specific routes
    Route::get('/{id}', [AiKnowledgeController::class, 'show'])->name('ai.knowledge.show');
    Route::delete('/{id}', [AiKnowledgeController::class, 'destroy'])->name('ai.knowledge.destroy');
    Route::post('/{id}/reindex', [AiKnowledgeController::class, 'reindex'])->name('ai.knowledge.reindex');
});
