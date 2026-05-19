<?php

declare(strict_types=1);

namespace Domain\Ai\Listeners;

use Domain\Ai\Events\AiRunDelegated;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Persiste o agente alvo da delegação como sticky agent no ticket.
 *
 * Quando um agente IA delega para um especialista, este listener grava
 * current_ai_agent_id no chat_ticket. Mensagens seguintes do mesmo ticket
 * vão direto ao especialista, pulando o roteador (Sofia).
 */
final class AiRunDelegatedStickyAgentListener
{
    public function handle(AiRunDelegated $event): void
    {
        $ticketId = (string) ($event->payload['ticket_id'] ?? '');

        if ($ticketId === '') {
            $childRun = AiAutopilotRun::query()
                ->where('tenant_id', $event->tenantId)
                ->find($event->childRunId);

            $childContext = is_array($childRun?->input_context) ? $childRun->input_context : [];
            $ticketId = (string) ($childContext['ticket_id'] ?? '');
        }

        if ($ticketId === '') {
            return;
        }

        // Ticket ID must be a valid UUID — skip if not (e.g. legacy string IDs)
        if (! Str::isUuid($ticketId)) {
            Log::debug('[StickyAgent] Ticket ID is not a valid UUID, skipping sticky write', [
                'ticket_id' => $ticketId,
            ]);

            return;
        }

        $ticket = ChatTicket::query()
            ->where('tenant_id', $event->tenantId)
            ->find($ticketId);

        if (! $ticket instanceof ChatTicket) {
            return;
        }

        $stickyAgent = AiAgent::query()
            ->where('tenant_id', $event->tenantId)
            ->where('is_active', true)
            ->find($event->targetAgentId);

        if (! $stickyAgent instanceof AiAgent) {
            Log::warning('[StickyAgent] Target agent not found or inactive, skipping sticky write', [
                'tenant_id' => $event->tenantId,
                'ticket_id' => $ticketId,
                'target_agent_id' => $event->targetAgentId,
            ]);

            return;
        }

        $ticket->current_ai_agent_id = $stickyAgent->id;
        $ticket->save();

        Log::info('[StickyAgent] Sticky agent updated', [
            'tenant_id' => $event->tenantId,
            'ticket_id' => $ticketId,
            'current_ai_agent_id' => (string) $stickyAgent->id,
        ]);
    }
}
