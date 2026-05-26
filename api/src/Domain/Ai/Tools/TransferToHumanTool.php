<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Chat\Models\ChatTicket;

/**
 * Ferramenta de IA para transferir um ticket para atendimento humano.
 *
 * Input esperado: ticket_id e reason (obrigatórios); priority opcional.
 * Output produzido: ticket_id, flag was_already_transferred e prioridade aplicada.
 * Quando usar: a IA não conseguir atender a solicitação ou o cliente pedir atendimento humano.
 * Operação idempotente — retorna sucesso mesmo se já transferido.
 * Segurança: valida tenant_id para evitar acesso entre tenants.
 */
class TransferToHumanTool implements AiToolInterface
{
    /** Executa a transferência do ticket para atendimento humano. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $ticketId = $input->parameters['ticket_id'] ?? null;
        $reason = $input->parameters['reason'] ?? '';
        $priority = $input->parameters['priority'] ?? 'normal';
        $tenantId = $input->context['tenant_id'] ?? null;

        // Tenant isolation: only access tickets from the same tenant
        $ticket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->find($ticketId);

        if (! $ticket) {
            return ToolResultDTO::failure('Ticket not found');
        }

        // Idempotent - always succeed even if already transferred
        if (! $ticket->is_bot_active) {
            return ToolResultDTO::success(
                message: 'Ticket was already transferred to human',
                data: [
                    'ticket_id' => $ticket->id,
                    'was_already_transferred' => true,
                    'priority' => $ticket->priority,
                ]
            );
        }

        $ticket->loadMissing('extended');
        $ticket->is_bot_active = false;
        $ticket->current_ai_agent_id = null;
        if ($ticket->human_takeover_at === null) {
            $ticket->human_takeover_at = now();
        }
        $ticket->priority = $priority;
        $ticket->metadata = array_merge($ticket->metadata ?? [], [
            'ai_transfer_reason' => $reason,
            'ai_transferred_at' => now()->toIso8601String(),
        ]);
        $ticket->save();

        return ToolResultDTO::success(
            message: 'Ticket transferred to human successfully',
            data: [
                'ticket_id' => $ticket->id,
                'was_already_transferred' => false,
                'priority' => $priority,
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::TRANSFER_TO_HUMAN;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Transfers the conversation to a human agent when the AI cannot handle the request or the customer requests human support.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'ticket_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of the ticket to transfer',
            ],
            'reason' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Reason for the transfer',
            ],
            'priority' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Priority level: low, normal, high, urgent',
            ],
        ];
    }
}
