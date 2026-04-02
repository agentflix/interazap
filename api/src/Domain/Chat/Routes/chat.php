<?php

declare(strict_types=1);

/**
 * Rotas do Módulo de Chat.
 *
 * Define os endpoints para gestão de tickets, mensagens, automações (chatbot),
 * campanhas e integrações de instâncias WhatsApp.
 */
use Domain\Chat\Http\Controllers\ChatCampaignController;
use Domain\Chat\Http\Controllers\ChatChatbotRuleController;
use Domain\Chat\Http\Controllers\ChatInstanceController;
use Domain\Chat\Http\Controllers\ChatMediaController;
use Domain\Chat\Http\Controllers\ChatMessageController;
use Domain\Chat\Http\Controllers\ChatMessageTemplateController;
use Domain\Chat\Http\Controllers\ChatPresenceController;
use Domain\Chat\Http\Controllers\ChatQuickAnswerController;
use Domain\Chat\Http\Controllers\ChatTicketControlController;
use Domain\Chat\Http\Controllers\ChatTicketController;
use Domain\Chat\Http\Controllers\ChatTicketEvaluationController;
use Domain\Chat\Http\Controllers\ChatTicketEvaluationPublicController;
use Domain\Chat\Http\Controllers\ChatTicketTransferController;
use Domain\Chat\Http\Controllers\ChatWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook Routes with Rate Limiting (SEC-001)
|--------------------------------------------------------------------------
| Webhook routes are protected with rate limiting to prevent
| brute force attacks and enumeration of valid tokens.
*/
Route::middleware(['throttle:webhooks'])->group(function (): void {
    Route::post('/webhooks/uazapi/instances/{token}', [ChatWebhookController::class, 'uazapi']);
});

Route::middleware(['auth:sanctum', 'throttle:chat'])->withoutMiddleware('throttle:api')->group(function (): void {
    Route::prefix('chat')->group(function (): void {
        Route::get('init', [ChatTicketController::class, 'init']);
        Route::get('tickets', [ChatTicketController::class, 'index']);
        Route::post('tickets', [ChatTicketController::class, 'store']);
        Route::get('tickets/{id}', [ChatTicketController::class, 'show']);
        Route::match(['post', 'patch'], 'tickets/{id}/open', [ChatTicketController::class, 'open']);
        Route::post('tickets/{id}/read', [ChatTicketController::class, 'read']);
        Route::post('tickets/{id}/transfer', [ChatTicketController::class, 'transfer']);
        Route::post('tickets/{id}/close', [ChatTicketController::class, 'close']);
        Route::post('tickets/{id}/takeover', [ChatTicketControlController::class, 'takeover']);
        Route::post('tickets/{id}/release-to-ai', [ChatTicketControlController::class, 'releaseToAi']);

        Route::get('tickets/{ticketId}/messages', [ChatMessageController::class, 'index']);
        Route::post('tickets/{ticketId}/messages', [ChatMessageController::class, 'store']);
        Route::get('tickets/{ticketId}/messages/{messageId}/media', [ChatMessageController::class, 'media']);
        Route::post('tickets/{ticketId}/messages/{messageId}/react', [ChatMessageController::class, 'react']);
        Route::post('tickets/{ticketId}/messages/{messageId}/edit', [ChatMessageController::class, 'edit']);
        Route::delete('tickets/{ticketId}/messages/{messageId}', [ChatMessageController::class, 'destroy']);
        Route::post('tickets/{ticketId}/messages/contact', [ChatMessageController::class, 'sendContact']);
        Route::post('tickets/{ticketId}/messages/location', [ChatMessageController::class, 'sendLocation']);
        Route::post('tickets/{ticketId}/presence', [ChatPresenceController::class, 'store']);
        Route::get('tickets/{ticketId}/evaluations', [ChatTicketEvaluationController::class, 'index']);
        Route::post('tickets/{ticketId}/evaluations', [ChatTicketEvaluationController::class, 'store']);

        Route::get('tickets/{ticketId}/transfers', [ChatTicketTransferController::class, 'index']);
        Route::post('tickets/{ticketId}/transfers', [ChatTicketTransferController::class, 'store']);

        Route::get('message-templates', [ChatMessageTemplateController::class, 'index']);
        Route::post('message-templates', [ChatMessageTemplateController::class, 'store']);

        Route::get('quick-answers', [ChatQuickAnswerController::class, 'index']);
        Route::get('quick-answers/all', [ChatQuickAnswerController::class, 'all']);
        Route::post('quick-answers', [ChatQuickAnswerController::class, 'store']);
        Route::get('quick-answers/{id}', [ChatQuickAnswerController::class, 'show']);
        Route::put('quick-answers/{id}', [ChatQuickAnswerController::class, 'update']);
        Route::delete('quick-answers/{id}', [ChatQuickAnswerController::class, 'destroy']);

        Route::get('chatbot/rules', [ChatChatbotRuleController::class, 'index']);
        Route::get('chatbot/rules/validate-keyword', [ChatChatbotRuleController::class, 'validateKeyword']);
        Route::post('chatbot/rules', [ChatChatbotRuleController::class, 'store']);
        Route::get('chatbot/rules/{id}', [ChatChatbotRuleController::class, 'show']);
        Route::put('chatbot/rules/{id}', [ChatChatbotRuleController::class, 'update']);
        Route::delete('chatbot/rules/{id}', [ChatChatbotRuleController::class, 'destroy']);

        Route::get('campaigns', [ChatCampaignController::class, 'index']);
        Route::post('campaigns', [ChatCampaignController::class, 'store']);
        Route::post('campaigns/preview', [ChatCampaignController::class, 'preview']);
        Route::post('campaigns/audience', [ChatCampaignController::class, 'audience']);
        Route::get('campaigns/{id}', [ChatCampaignController::class, 'show']);
        Route::put('campaigns/{id}', [ChatCampaignController::class, 'update']);
        Route::post('campaigns/{id}/send', [ChatCampaignController::class, 'send']);
        Route::delete('campaigns/{id}', [ChatCampaignController::class, 'destroy']);
        Route::post('media', [ChatMediaController::class, 'store']);
    });

    Route::prefix('integrations')->group(function (): void {
        Route::get('/', [ChatInstanceController::class, 'index']);
        Route::post('/', [ChatInstanceController::class, 'store']);
        Route::get('{id}', [ChatInstanceController::class, 'show']);
        Route::put('{id}', [ChatInstanceController::class, 'update']);
        Route::delete('{id}', [ChatInstanceController::class, 'destroy']);
        Route::patch('{id}/toggle-active', [ChatInstanceController::class, 'toggleActive']);
        Route::post('{id}/connect', [ChatInstanceController::class, 'connect']);
        Route::get('{id}/status', [ChatInstanceController::class, 'status']);
        Route::post('{id}/disconnect', [ChatInstanceController::class, 'disconnect']);
        Route::post('{id}/profile-image', [ChatInstanceController::class, 'profileImage']);
        Route::post('{id}/presence', [ChatInstanceController::class, 'presence']);
    });
});

Route::middleware(['throttle:public'])->group(function (): void {
    Route::get('/public/chat/evaluations/{token}', [ChatTicketEvaluationPublicController::class, 'show']);
    Route::post('/public/chat/evaluations/{token}', [ChatTicketEvaluationPublicController::class, 'submit']);
});

Route::get('/chat/media/download', [ChatMediaController::class, 'download'])
    ->name('chat.media.download')
    ->middleware('signed');
