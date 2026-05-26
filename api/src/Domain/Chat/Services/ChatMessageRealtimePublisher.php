<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;

/**
 * Publica payloads compactos de mensagens e tickets em tempo real via WebSocket.
 *
 * Sanitiza metadados sensíveis (raw, JPEGThumbnail, base64) antes da transmissão
 * para evitar payloads volumosos no canal de atividade do chat.
 *
 * @category Services
 */
final class ChatMessageRealtimePublisher
{
    public function __construct(
        private readonly ChatActivityBroadcastService $activityBroadcast,
    ) {}

    /**
     * Emite evento de nova mensagem recebida/enviada no canal de atividade do ticket.
     *
     * Inclui dados sanitizados da mensagem e do ticket. Propaga client_message_id
     * quando presente nos metadados para reconciliação no frontend.
     *
     * @param  ChatMessage  $message  Mensagem a publicar.
     * @param  ChatTicket|null  $ticket  Ticket relacionado (incluído no payload se fornecido).
     */
    public function emitNewMessage(ChatMessage $message, ?ChatTicket $ticket): void
    {
        $ticketData = $ticket instanceof ChatTicket ? $this->sanitizeTicket($ticket) : null;

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $clientMessageId = isset($metadata['client_message_id']) && is_string($metadata['client_message_id'])
            ? $metadata['client_message_id']
            : null;

        $payload = [
            'ticket_id' => (string) $message->ticket_id,
            'tenant_id' => (string) $message->tenant_id,
            'message' => $this->sanitizeMessage($message),
        ];

        if ($clientMessageId !== null) {
            $payload['client_message_id'] = $clientMessageId;
        }

        $this->activityBroadcast->emitMessageReceived(
            (string) $message->ticket_id,
            $payload,
            $ticketData,
        );
    }

    /**
     * Emite evento de atualização de status de mensagem (sent, delivered, read, failed).
     *
     * @param  ChatMessage  $message  Mensagem com status atualizado.
     */
    public function emitStatus(ChatMessage $message): void
    {
        $message->loadMissing('extended');
        $this->activityBroadcast->emitMessageStatus((string) $message->ticket_id, [
            'message_id' => (string) $message->id,
            'ticket_id' => (string) $message->ticket_id,
            'tenant_id' => (string) $message->tenant_id,
            'status' => $message->status,
            'error_message' => $message->error_message,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
        ]);
    }

    /**
     * Converte a mensagem para array sanitizado, removendo metadados volumosos.
     *
     * @return array<string, mixed> Dados da mensagem prontos para transmissão.
     */
    private function sanitizeMessage(ChatMessage $message): array
    {
        return $this->sanitizeMessageArray($message->toArray());
    }

    /**
     * Converte o ticket para array sanitizado, removendo metadados volumosos da última mensagem.
     *
     * @return array<string, mixed> Dados do ticket prontos para transmissão.
     */
    private function sanitizeTicket(ChatTicket $ticket): array
    {
        $data = $ticket->toArray();

        if (isset($data['latest_message']) && is_array($data['latest_message'])) {
            $data['latest_message'] = $this->sanitizeMessageArray($data['latest_message']);
        }

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = $this->sanitizeMetadata($data['metadata']);
        }

        return $data;
    }

    /**
     * Sanitiza os metadados de um array de mensagem.
     *
     * @param  array<string, mixed>  $data  Dados brutos da mensagem.
     * @return array<string, mixed> Dados sanitizados.
     */
    private function sanitizeMessageArray(array $data): array
    {
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = $this->sanitizeMetadata($data['metadata']);
        }

        return $data;
    }

    /**
     * Remove chaves sensíveis ou volumosas dos metadados da mensagem.
     *
     * @param  array<string, mixed>  $metadata  Metadados brutos.
     * @return array<string, mixed> Metadados sem as chaves banidas.
     */
    private function sanitizeMetadata(array $metadata): array
    {
        return $this->stripKeysRecursive($metadata, ['raw', 'JPEGThumbnail', 'base64', 'profilePicThumbObj']);
    }

    /**
     * Remove recursivamente as chaves especificadas de um array aninhado.
     *
     * @param  array<string, mixed>  $data  Array de dados.
     * @param  list<string>  $keys  Chaves a remover em todos os níveis.
     * @return array<string, mixed> Array sem as chaves removidas.
     */
    private function stripKeysRecursive(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                unset($data[$key]);
            }
        }

        foreach ($data as $index => $value) {
            if (is_array($value)) {
                $data[$index] = $this->stripKeysRecursive($value, $keys);
            }
        }

        return $data;
    }
}
