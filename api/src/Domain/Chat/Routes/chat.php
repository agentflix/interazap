<?php

declare(strict_types=1);

/**
 * Rotas do Módulo de Chat.
 *
 * Define os endpoints para gestão de tickets, mensagens, automações (auto-reply),
 * campanhas e canais de instâncias WhatsApp.
 */
use Domain\Chat\Http\Controllers\ChatAutoReplyRuleController;
use Domain\Chat\Http\Controllers\ChatInstanceController;
use Domain\Chat\Http\Controllers\ChatMediaController;
use Domain\Chat\Http\Controllers\ChatMessageController;
use Domain\Chat\Http\Controllers\ChatMessageTemplateController;
use Domain\Chat\Http\Controllers\ChatPresenceController;
use Domain\Chat\Http\Controllers\ChatQuickAnswerController;
use Domain\Chat\Http\Controllers\ChatRoutingQueueController;
use Domain\Chat\Http\Controllers\ChatTicketControlController;
use Domain\Chat\Http\Controllers\ChatTicketController;
use Domain\Chat\Http\Controllers\ChatTicketEvaluationController;
use Domain\Chat\Http\Controllers\ChatTicketEvaluationPublicController;
use Domain\Chat\Http\Controllers\ChatTicketTransferController;
use Domain\Chat\Http\Controllers\ChatTransmissionListController;
use Domain\Chat\Http\Controllers\ChatWebhookController;
use Domain\Chat\Http\Controllers\ChatWindowController;
use Domain\Chat\Http\Controllers\Internal\InternalChannelLookupController;
use Domain\Chat\Http\Controllers\Internal\InternalChatInstanceController;
use Domain\Chat\Http\Controllers\Internal\InternalRealtimeController;
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
    Route::post('/webhooks/telegram/instances/{token}', [ChatWebhookController::class, 'telegram'])
        ->name('chat.webhooks.telegram');
});

/*
|--------------------------------------------------------------------------
| Gateway Routes with GATEWAY_SECRET
|--------------------------------------------------------------------------
| Routes accessed by the Gateway service using GATEWAY_SECRET authentication.
| These routes are not tenant-isolated as they use global lookups.
*/
Route::middleware(['gateway.secret'])->prefix('chat')->group(function (): void {
    Route::get('instances/by-phone-number/{phoneNumberId}', [ChatWindowController::class, 'lookupByPhoneNumber']);
});

Route::middleware(['gateway.secret'])->prefix('internal/chat')->group(function (): void {
    Route::get('instances/by-waba/{wabaId}', [InternalChannelLookupController::class, 'byWaba']);
    Route::get('instances/by-webhook-token/{token}', [InternalChatInstanceController::class, 'byWebhookToken']);
    Route::get('instances/{id}', [InternalChatInstanceController::class, 'show']);
    Route::patch('instances/{id}/connection-status', [InternalChatInstanceController::class, 'updateConnectionStatus']);
    Route::get('instances', [InternalChatInstanceController::class, 'index']);
});

Route::middleware(['gateway.secret'])->prefix('internal/realtime')->group(function (): void {
    Route::get('room-access', [InternalRealtimeController::class, 'roomAccess']);
});

Route::middleware(['auth:sanctum', 'throttle:chat'])->withoutMiddleware('throttle:api')->group(function (): void {
    Route::prefix('chat')->group(function (): void {
        Route::get('init', [ChatTicketController::class, 'init']);
        Route::get('contacts/{id}/window-status', [ChatWindowController::class, 'windowStatus']);
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
        Route::post('tickets/{ticketId}/messages/template', [ChatMessageController::class, 'sendTemplate']);
        Route::post('tickets/{ticketId}/presence', [ChatPresenceController::class, 'store']);
        Route::get('tickets/{ticketId}/evaluations', [ChatTicketEvaluationController::class, 'index']);
        Route::post('tickets/{ticketId}/evaluations', [ChatTicketEvaluationController::class, 'store']);

        Route::get('tickets/{ticketId}/transfers', [ChatTicketTransferController::class, 'index']);
        Route::post('tickets/{ticketId}/transfers', [ChatTicketTransferController::class, 'store']);

        Route::get('message-templates', [ChatMessageTemplateController::class, 'index']);
        Route::post('message-templates', [ChatMessageTemplateController::class, 'store']);
        Route::post('message-templates/sync', [ChatMessageTemplateController::class, 'sync']);
        Route::get('message-templates/{id}', [ChatMessageTemplateController::class, 'show']);
        Route::put('message-templates/{id}', [ChatMessageTemplateController::class, 'update']);
        Route::delete('message-templates/{id}', [ChatMessageTemplateController::class, 'destroy']);

        Route::get('quick-answers', [ChatQuickAnswerController::class, 'index']);
        Route::get('quick-answers/all', [ChatQuickAnswerController::class, 'all']);
        Route::post('quick-answers', [ChatQuickAnswerController::class, 'store']);
        Route::get('quick-answers/{id}', [ChatQuickAnswerController::class, 'show']);
        Route::put('quick-answers/{id}', [ChatQuickAnswerController::class, 'update']);
        Route::delete('quick-answers/{id}', [ChatQuickAnswerController::class, 'destroy']);

        Route::get('auto-reply/rules', [ChatAutoReplyRuleController::class, 'index']);
        Route::get('auto-reply/rules/validate-keyword', [ChatAutoReplyRuleController::class, 'validateKeyword']);
        Route::post('auto-reply/rules', [ChatAutoReplyRuleController::class, 'store']);
        Route::get('auto-reply/rules/{id}', [ChatAutoReplyRuleController::class, 'show']);
        Route::put('auto-reply/rules/{id}', [ChatAutoReplyRuleController::class, 'update']);
        Route::delete('auto-reply/rules/{id}', [ChatAutoReplyRuleController::class, 'destroy']);

        Route::get('transmission-lists', [ChatTransmissionListController::class, 'index']);
        Route::post('transmission-lists', [ChatTransmissionListController::class, 'store']);
        Route::post('transmission-lists/preview', [ChatTransmissionListController::class, 'preview']);
        Route::post('transmission-lists/audience', [ChatTransmissionListController::class, 'audience']);
        Route::get('transmission-lists/{id}', [ChatTransmissionListController::class, 'show']);
        Route::put('transmission-lists/{id}', [ChatTransmissionListController::class, 'update']);
        Route::post('transmission-lists/{id}/send', [ChatTransmissionListController::class, 'send']);
        Route::delete('transmission-lists/{id}', [ChatTransmissionListController::class, 'destroy']);
        Route::post('media', [ChatMediaController::class, 'store']);

        Route::get('channels/{id}/routing-queue', [ChatRoutingQueueController::class, 'showChannel']);
        Route::post('channels/{id}/routing-queue', [ChatRoutingQueueController::class, 'storeChannel']);
        Route::put('channels/{id}/routing-queue', [ChatRoutingQueueController::class, 'updateChannel']);

        Route::get('channels/{id}/routing-queue/agents', [ChatRoutingQueueController::class, 'indexAgents'])->defaults('scope', 'channel');
        Route::post('channels/{id}/routing-queue/agents', [ChatRoutingQueueController::class, 'storeAgent'])->defaults('scope', 'channel');
        Route::delete('channels/{id}/routing-queue/agents/{userId}', [ChatRoutingQueueController::class, 'destroyAgent'])->defaults('scope', 'channel');
        Route::put('channels/{id}/routing-queue/agents/reorder', [ChatRoutingQueueController::class, 'reorderAgents'])->defaults('scope', 'channel');

        Route::get('channels/{id}/routing-queue/agents/{userId}/skills', [ChatRoutingQueueController::class, 'indexSkills'])->defaults('scope', 'channel');
        Route::post('channels/{id}/routing-queue/agents/{userId}/skills', [ChatRoutingQueueController::class, 'storeSkill'])->defaults('scope', 'channel');
        Route::delete('channels/{id}/routing-queue/agents/{userId}/skills/{skill}', [ChatRoutingQueueController::class, 'destroySkill'])->defaults('scope', 'channel');

        Route::get('routing-queue/global', [ChatRoutingQueueController::class, 'showGlobal']);
        Route::post('routing-queue/global', [ChatRoutingQueueController::class, 'storeGlobal']);
        Route::put('routing-queue/global', [ChatRoutingQueueController::class, 'updateGlobal']);

        Route::get('routing-queue/global/agents', [ChatRoutingQueueController::class, 'indexAgents'])->defaults('scope', 'global');
        Route::post('routing-queue/global/agents', [ChatRoutingQueueController::class, 'storeAgent'])->defaults('scope', 'global');
        Route::delete('routing-queue/global/agents/{userId}', [ChatRoutingQueueController::class, 'destroyAgent'])->defaults('scope', 'global');
        Route::put('routing-queue/global/agents/reorder', [ChatRoutingQueueController::class, 'reorderAgents'])->defaults('scope', 'global');

        Route::get('routing-queue/global/agents/{userId}/skills', [ChatRoutingQueueController::class, 'indexSkills'])->defaults('scope', 'global');
        Route::post('routing-queue/global/agents/{userId}/skills', [ChatRoutingQueueController::class, 'storeSkill'])->defaults('scope', 'global');
        Route::delete('routing-queue/global/agents/{userId}/skills/{skill}', [ChatRoutingQueueController::class, 'destroySkill'])->defaults('scope', 'global');
    });

    Route::prefix('channels')->group(function (): void {
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
