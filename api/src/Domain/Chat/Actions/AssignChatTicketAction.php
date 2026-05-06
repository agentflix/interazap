<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Configuration\Events\TicketAssignedEvent;

/**
 * Atribuir tickets, ativar human takeover e liberar para IA.
 *
 * Responsabilidades:
 * - Transferir ticket para usuário/departamento
 * - Ativar bloqueio de automações (human takeover)
 * - Liberar para IA/Bot automático
 * - Emitir eventos de atribuição e automação
 */
final class AssignChatTicketAction
{
    public function __construct(
        private readonly ChatActivityBroadcastService $activityBroadcast,
        private readonly SendTicketMessageAction $messageAction,
    ) {}

    /**
     * Transferir ticket para um usuário ou departamento.
     */
    public function transfer(ChatTicket $ticket, ?string $userId, ?string $departmentId): ChatTicket
    {
        if ($userId) {
            $ticket->assigned_to = $userId;
            if ($ticket->status === 'pending') {
                $ticket->status = 'open';
            }
        }

        $ticket->save();

        if ($departmentId && ! $this->isAiBusinessHoursMode($ticket)) {
            $this->messageAction->sendConfiguredSystemMessage(
                $ticket,
                'send_department_transfer_message',
                'department_transfer_message',
                'department_transfer_message',
                ['department_id' => $departmentId]
            );
        }

        if ($userId) {
            // Transferência para humano ativa takeover, encerrando o fluxo de IA neste ticket.
            $this->activateHumanTakeover((string) $ticket->tenant_id, (string) $ticket->id, $userId);

            TicketAssignedEvent::dispatch((string) $ticket->tenant_id, (string) $ticket->id, (string) $userId);
        }

        return $ticket;
    }

    /**
     * Ativar atendimento humano bloqueando automações.
     *
     * @param  string|null  $userId  ID do agente que ativou
     */
    public function activateHumanTakeover(string $tenantId, string $ticketId, ?string $userId = null): ChatTicket
    {
        $ticket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($ticketId);

        if (! $ticket->human_takeover_at) {
            $ticket->loadMissing('extended');
            $ticket->human_takeover_at = now();
            $ticket->save();
            $ticket->flushPendingExtended();

            \Illuminate\Support\Facades\Log::info('[HumanTakeover] Atendimento humano ativado', [
                'ticket_id' => $ticketId,
                'tenant_id' => $tenantId,
                'agent_id' => $userId,
            ]);

            $ticket->load(['latestMessage', 'contact']);
            $this->activityBroadcast->emit(
                (string) $ticket->id,
                [
                    [
                        'type' => 'ticket.updated',
                        'data' => [
                            'ticket_id' => (string) $ticket->id,
                            'tenant_id' => (string) $ticket->tenant_id,
                            'ticket' => $ticket->toArray(),
                            'event_type' => 'human_takeover_activated',
                        ],
                    ],
                ],
                (string) $ticket->tenant_id
            );
        }

        return $ticket;
    }

    /**
     * Liberar ticket para automações de IA/Bot.
     *
     * @param  string|null  $userId  ID do agente que liberou
     */
    public function releaseToAi(string $tenantId, string $ticketId, ?string $userId = null): ChatTicket
    {
        $ticket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($ticketId);

        $previousTakeoverAt = $ticket->human_takeover_at;

        if ($ticket->human_takeover_at) {
            $ticket->loadMissing('extended');
            $ticket->human_takeover_at = null;
            $ticket->current_ai_agent_id = null;
            $ticket->save();
            $ticket->flushPendingExtended();

            $durationMinutes = $previousTakeoverAt ? now()->diffInMinutes($previousTakeoverAt) : 0;
            AutopilotTriggerFired::dispatch(
                $tenantId,
                AutopilotTriggerType::HUMAN_TAKEOVER_ENDED,
                [
                    'ticket_id' => (string) $ticket->id,
                    'agent_id' => (string) ($userId ?? ''),
                    'duration_minutes' => $durationMinutes,
                    'source_type' => 'ticket',
                ],
                (string) $ticket->id,
            );

            \Illuminate\Support\Facades\Log::info('[HumanTakeover] Controle devolvido para IA', [
                'ticket_id' => $ticketId,
                'tenant_id' => $tenantId,
                'agent_id' => $userId,
            ]);

            $ticket->load(['latestMessage', 'contact']);
            $this->activityBroadcast->emit(
                (string) $ticket->id,
                [
                    [
                        'type' => 'ticket.updated',
                        'data' => [
                            'ticket_id' => (string) $ticket->id,
                            'tenant_id' => (string) $ticket->tenant_id,
                            'ticket' => $ticket->toArray(),
                            'event_type' => 'released_to_ai',
                        ],
                    ],
                ],
                (string) $ticket->tenant_id
            );
        }

        return $ticket;
    }

    /**
     * Verificar se a instância está em modo IA com regra de horário.
     */
    private function isAiBusinessHoursMode(ChatTicket $ticket): bool
    {
        if (! $ticket->instance_id) {
            return false;
        }

        $instance = \Domain\Chat\Models\ChatInstance::query()
            ->select(['id', 'tenant_id', 'mode', 'settings_json'])
            ->where('tenant_id', $ticket->tenant_id)
            ->find($ticket->instance_id);

        if (! $instance) {
            return false;
        }

        $candidates = [];
        if (is_string($instance->mode) && $instance->mode !== '') {
            $candidates[] = mb_strtolower($instance->mode);
        }

        $settings = is_array($instance->settings_json) ? $instance->settings_json : [];
        foreach (['mode', 'operation_mode', 'service_mode', 'attendance_mode', 'ai_mode'] as $key) {
            $value = $settings[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $candidates[] = mb_strtolower($value);
            }
        }

        foreach ($candidates as $candidate) {
            $hasAi = str_contains($candidate, 'ia') || str_contains($candidate, 'ai');
            $hasBusinessHours = str_contains($candidate, 'horario')
                || str_contains($candidate, 'exped')
                || str_contains($candidate, 'business');

            if ($hasAi && $hasBusinessHours) {
                return true;
            }
        }

        return false;
    }
}
