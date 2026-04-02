<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Chat\Models\ChatTicket;

/**
 * Tool to close a support ticket.
 *
 * Security: Validates tenant_id to prevent cross-tenant access.
 */
class CloseTicketTool implements AiToolInterface
{
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $ticketId = $input->parameters['ticket_id'] ?? null;
        $reason = $input->parameters['reason'] ?? 'resolved';
        $summary = $input->parameters['summary'] ?? null;
        $tenantId = $input->context['tenant_id'] ?? null;

        // Tenant isolation: only access tickets from the same tenant
        $ticket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->find($ticketId);

        if (! $ticket) {
            return ToolResultDTO::failure('Ticket not found');
        }

        if ($ticket->status === 'closed') {
            return ToolResultDTO::failure('Ticket is already closed');
        }

        $ticket->loadMissing('extended');
        $ticket->status = 'closed';
        $ticket->close_reason = $reason;
        $ticket->closed_at = now();
        $ticket->closed_by = null; // AI-closed, no user
        $ticket->metadata = array_merge($ticket->metadata ?? [], [
            'ai_close_summary' => $summary,
            'closed_by_ai' => true,
        ]);
        $ticket->save();

        return ToolResultDTO::success(
            message: 'Ticket closed successfully',
            data: [
                'ticket_id' => $ticket->id,
                'status' => 'closed',
                'reason' => $reason,
            ]
        );
    }

    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::CLOSE_TICKET;
    }

    public function getDescription(): string
    {
        return 'Closes a support ticket when the issue is resolved or the conversation is complete. Requires a reason for closing.';
    }

    /**
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'ticket_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of the ticket to close',
            ],
            'reason' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Reason for closing: resolved, abandoned, spam, duplicate',
            ],
            'summary' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Summary of the resolution or conversation',
            ],
        ];
    }
}
