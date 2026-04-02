<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Ai\Events\AiRunRequested;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Services\PlatformPlanEnforcementService;

/**
 * Router for inbound webhook messages.
 *
 * Routing is determined by the tenant's contracted plan:
 * - Plan with ai_enabled=true → AI Autopilot flow
 * - Plan without AI or no plan → Auto Reply flow (rule-based)
 *
 * The instance mode field is NOT used for routing decisions.
 */
final class ChatWebhookRouter
{
    public function __construct(
        private readonly ChatAutoReplyResponder $autoReplyResponder,
        private readonly PlatformPlanEnforcementService $planEnforcement,
    ) {}

    /**
     * Routes inbound messages to the proper automation flow.
     *
     * @param  string  $tenantId  Tenant identifier.
     * @param  ChatTicket  $ticket  Related ticket.
     * @param  string  $body  Incoming message content.
     * @param  array<string, mixed>  $context  Context data (instance_id, message_id, message_type, is_first_interaction).
     */
    public function routeInbound(string $tenantId, ChatTicket $ticket, string $body, array $context = []): void
    {
        if (trim($body) === '') {
            return;
        }

        $ticket->loadMissing('extended');

        if ($ticket->human_takeover_at) {
            logger()->info('[ChatWebhookRouter] Auto-resposta ignorada: atendimento humano em controle', [
                'ticket_id' => (string) $ticket->id,
                'instance_id' => (string) ($context['instance_id'] ?? ''),
            ]);

            return;
        }

        $isFirstInteraction = (bool) ($context['is_first_interaction'] ?? false);
        $aiEnabled = $this->planEnforcement->isAiEnabled($tenantId);

        logger()->info('[ChatWebhookRouter] Roteamento por plano', [
            'ai_enabled' => $aiEnabled,
            'ticket_id' => (string) $ticket->id,
            'instance_id' => (string) ($context['instance_id'] ?? ''),
            'is_first_interaction' => $isFirstInteraction,
        ]);

        if ($aiEnabled) {
            $this->handleAiFlow($tenantId, $ticket, $body, $context);

            return;
        }

        $this->autoReplyResponder->dispatch($tenantId, (string) $ticket->id, $body, $isFirstInteraction);
    }

    /**
     * Handles the AI Autopilot flow.
     *
     * @param  string  $tenantId  Tenant identifier.
     * @param  ChatTicket  $ticket  Related ticket.
     * @param  string  $body  Incoming message content.
     * @param  array<string, mixed>  $context  Context data.
     */
    private function handleAiFlow(string $tenantId, ChatTicket $ticket, string $body, array $context): void
    {
        AiRunRequested::dispatch(
            $tenantId,
            (string) $ticket->id,
            $body,
            $context,
        );
    }
}
