<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatActivityBroadcastService;

/**
 * Action de processamento, sanitização e broadcast de mensagens de chat.
 *
 * Responsável pela emissão de eventos WebSocket (status e novas mensagens)
 * e sanitização de payloads para transmissão em tempo real.
 *
 * @category Actions
 */
final readonly class ProcessChatMessageAction
{
    public function __construct(
        private ChatActivityBroadcastService $activityBroadcast,
    ) {}

    /**
     * Emitir evento WebSocket de atualização de status da mensagem.
     *
     * @param  ChatMessage  $message  Mensagem com status atualizado.
     */
    public function emitMessageStatusEvent(ChatMessage $message): void
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
     * Emitir evento WebSocket de nova mensagem para atualização em tempo real.
     *
     * @param  ChatMessage  $message  Mensagem criada.
     * @param  ChatTicket|null  $ticket  Ticket associado.
     */
    public function emitNewMessageEvent(ChatMessage $message, ?ChatTicket $ticket): void
    {
        $ticketData = $ticket instanceof ChatTicket ? $this->sanitizeTicketForRealtime($ticket) : null;

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $clientMessageId = isset($metadata['client_message_id']) && is_string($metadata['client_message_id'])
            ? $metadata['client_message_id']
            : null;

        $payload = [
            'ticket_id' => (string) $message->ticket_id,
            'tenant_id' => (string) $message->tenant_id,
            'message' => $this->sanitizeMessageForRealtime($message),
        ];

        if ($clientMessageId !== null) {
            $payload['client_message_id'] = $clientMessageId;
        }

        $this->activityBroadcast->emitMessageReceived(
            (string) $message->ticket_id,
            $payload,
            $ticketData
        );
    }

    /**
     * Sanitizar mensagem para broadcast em tempo real.
     *
     * Remove blobs grandes (ex: JPEGThumbnail) e payloads brutos para
     * evitar limite de tamanho do broadcaster.
     *
     * @param  ChatMessage  $message  Mensagem persistida.
     * @return array<string, mixed> Payload compacto.
     */
    public function sanitizeMessageForRealtime(ChatMessage $message): array
    {
        return $this->sanitizeMessageArray($message->toArray());
    }

    /**
     * Sanitizar ticket para broadcast em tempo real.
     *
     * @param  ChatTicket  $ticket  Ticket persistido.
     * @return array<string, mixed> Payload compacto.
     */
    public function sanitizeTicketForRealtime(ChatTicket $ticket): array
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
     * Sanitizar estrutura de mensagem em array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeMessageArray(array $data): array
    {
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = $this->sanitizeMetadata($data['metadata']);
        }

        return $data;
    }

    /**
     * Remover chaves grandes/irrelevantes do metadata.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        return $this->stripKeysRecursive($metadata, ['raw', 'JPEGThumbnail', 'base64', 'profilePicThumbObj']);
    }

    /**
     * Remove recursivamente chaves de um array.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
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
