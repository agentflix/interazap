<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Chat\Models\ChatTicket;

/**
 * Ferramenta de IA para ler informações de um ticket e seu histórico de mensagens.
 *
 * Input esperado: ticket_id (obrigatório); include_messages e message_limit opcionais.
 * Output produzido: dados do ticket, informações do contato e opcionalmente histórico de mensagens.
 * Quando usar: contextualizar o agente sobre o atendimento em andamento antes de responder.
 * Segurança: valida tenant_id para evitar acesso entre tenants.
 */
class ReadTicketTool implements AiToolInterface
{
    /** Executa a leitura do ticket e seu histórico. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $ticketId = $input->parameters['ticket_id'] ?? null;
        $includeMessages = $input->parameters['include_messages'] ?? false;
        $messageLimit = (int) ($input->parameters['message_limit'] ?? 20);
        $tenantId = $input->context['tenant_id'] ?? null;

        // Tenant isolation: only access tickets from the same tenant
        $ticket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->with(['contact', 'extended'])
            ->find($ticketId);

        if (! $ticket) {
            return ToolResultDTO::failure('Ticket not found');
        }

        $data = [
            'ticket' => [
                'id' => $ticket->id,
                'protocol' => $ticket->protocol,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'channel' => $ticket->channel,
                'is_bot_active' => $ticket->is_bot_active,
                'tags' => $ticket->tags,
                'started_at' => $ticket->started_at?->toIso8601String(),
                'last_message_at' => $ticket->last_message_at?->toIso8601String(),
            ],
            'contact' => $ticket->contact ? [
                'id' => $ticket->contact->id,
                'name' => $ticket->contact->name,
                'phone' => $ticket->contact->phone,
                'email' => $ticket->contact->email,
            ] : null,
        ];

        if ($includeMessages) {
            $messages = $ticket->messages()
                ->orderBy('created_at', 'desc')
                ->limit($messageLimit)
                ->get()
                ->map(fn ($msg) => [
                    'id' => $msg->id,
                    'content' => $msg->content,
                    'type' => $msg->type,
                    'direction' => $msg->direction,
                    'source' => $msg->source,
                    'is_from_contact' => $msg->is_from_contact,
                    'created_at' => $msg->created_at->toIso8601String(),
                ])
                ->reverse()
                ->values()
                ->toArray();

            $data['messages'] = $messages;
        }

        return ToolResultDTO::success(
            message: 'Ticket retrieved successfully',
            data: $data
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::READ_TICKET;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Retrieves information about a support ticket including status, contact info, and optionally the conversation history.';
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
                'description' => 'UUID of the ticket to read',
            ],
            'include_messages' => [
                'type' => 'boolean',
                'required' => false,
                'description' => 'Whether to include conversation history',
            ],
            'message_limit' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Maximum number of messages to return (default: 20)',
            ],
        ];
    }
}
