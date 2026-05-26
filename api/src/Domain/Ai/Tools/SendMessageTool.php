<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Chat\Actions\SendChatMessageAction;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatTicket;

/**
 * Ferramenta de IA para enviar mensagens em um ticket de atendimento.
 *
 * Input esperado: ticket_id e content (obrigatórios); type, file_url, file_name, mime_type e file_size opcionais.
 * Output produzido: message_id, ticket_id e status da mensagem enfileirada.
 * Quando usar: responder ao cliente no contexto de um atendimento ativo.
 * Segurança: valida tenant_id para evitar acesso entre tenants.
 */
final class SendMessageTool implements AiToolInterface
{
    public function __construct(
        private SendChatMessageAction $messageActions,
    ) {}

    /** Executa o envio da mensagem no ticket. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $ticketId = $input->parameters['ticket_id'] ?? null;
        $content = $input->parameters['content'] ?? '';
        $type = $input->parameters['type'] ?? 'text';
        $fileUrl = $input->parameters['file_url'] ?? null;
        $fileName = $input->parameters['file_name'] ?? null;
        $mimeType = $input->parameters['mime_type'] ?? null;
        $fileSize = isset($input->parameters['file_size'])
            ? filter_var($input->parameters['file_size'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
            : null;
        $tenantId = $input->context['tenant_id'] ?? null;
        $agentName = $input->context['agent_name'] ?? null;

        if (empty(trim($content))) {
            return ToolResultDTO::failure('Message content cannot be empty');
        }

        // Tenant isolation: only access tickets from the same tenant
        $ticket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->find($ticketId);

        if (! $ticket) {
            return ToolResultDTO::failure('Ticket not found');
        }

        if ($ticket->status === 'closed') {
            return ToolResultDTO::failure('Cannot send message to a closed ticket');
        }

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => trim($content),
            'type' => $type,
            'direction' => 'outgoing',
            'is_from_contact' => false,
            'source' => 'ai',
            'file_url' => is_string($fileUrl) ? $fileUrl : null,
            'file_name' => is_string($fileName) ? $fileName : null,
            'mime_type' => is_string($mimeType) ? $mimeType : null,
            'file_size' => $fileSize !== false ? $fileSize : null,
            'metadata' => $agentName ? ['ai_agent_name' => $agentName] : [],
        ]);

        $message = $this->messageActions->create((string) $tenantId, $dto);

        return ToolResultDTO::success(
            message: 'Message sent successfully',
            data: [
                'message_id' => $message->id,
                'ticket_id' => $ticket->id,
                'status' => $message->status,
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::SEND_MESSAGE;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Sends a message to the customer in the context of a support ticket. The message will be queued for delivery via the appropriate channel.';
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
                'description' => 'UUID of the ticket to send the message to',
            ],
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Content of the message',
            ],
            'type' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Type of message: text, image, audio, document',
            ],
            'file_url' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Public URL for the attached file',
            ],
            'file_name' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Original file name',
            ],
            'mime_type' => [
                'type' => 'string',
                'required' => false,
                'description' => 'MIME type of the attached file',
            ],
            'file_size' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'File size in bytes',
            ],
        ];
    }
}
