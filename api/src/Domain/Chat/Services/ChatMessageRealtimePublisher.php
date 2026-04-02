<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;

/**
 * Publishes compact realtime payloads for chat messages and tickets.
 */
final class ChatMessageRealtimePublisher
{
    public function __construct(
        private readonly ChatActivityBroadcastService $activityBroadcast,
    ) {}

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
     * @return array<string, mixed>
     */
    private function sanitizeMessage(ChatMessage $message): array
    {
        return $this->sanitizeMessageArray($message->toArray());
    }

    /**
     * @return array<string, mixed>
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
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        return $this->stripKeysRecursive($metadata, ['raw', 'JPEGThumbnail', 'base64', 'profilePicThumbObj']);
    }

    /**
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
