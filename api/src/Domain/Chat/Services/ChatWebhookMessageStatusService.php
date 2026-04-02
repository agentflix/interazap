<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Models\ChatMessage;

/**
 * Applies delivery/read/delete updates for existing chat messages.
 */
final class ChatWebhookMessageStatusService
{
    public function __construct(
        private readonly ChatBroadcastService $broadcastService,
    ) {}

    /**
     * @param  array<string, mixed>  $message
     */
    public function update(string $tenantId, string $externalId, array $message): bool
    {
        $existing = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('external_id', $externalId)
            ->first();

        if (! $existing) {
            return false;
        }

        $originalStatus = $existing->status;
        $ack = $message['ack'] ?? $message['status'] ?? null;
        if (is_numeric($ack)) {
            $ack = (int) $ack;
            if ($ack >= 1) {
                $existing->status = 'sent';
                $existing->sent_at = $existing->sent_at ?? now();
            }
            if ($ack >= 2) {
                $existing->status = 'delivered';
                $existing->delivered_at = $existing->delivered_at ?? now();
            }
            if ($ack >= 3) {
                $existing->status = 'read';
                $existing->read_at = $existing->read_at ?? now();
            }
        } elseif (is_string($ack)) {
            $map = [
                'sent' => 'sent',
                'delivered' => 'delivered',
                'read' => 'read',
                'deleted' => 'deleted',
                'revoke' => 'deleted',
                'revoked' => 'deleted',
            ];
            $existing->status = $map[strtolower($ack)] ?? $existing->status;
            if ($existing->status === 'sent' && ! $existing->sent_at) {
                $existing->sent_at = now();
            }
            if ($existing->status === 'delivered' && ! $existing->delivered_at) {
                $existing->delivered_at = now();
            }
            if ($existing->status === 'read' && ! $existing->read_at) {
                $existing->read_at = now();
            }
            if ($existing->status === 'deleted' && ! $existing->deleted_at) {
                $existing->is_deleted = true;
                $existing->deleted_at = now();
                if (! $existing->deleted_by) {
                    $metadata = $existing->metadata ?? [];
                    $metadata['deleted_by'] = 'remote';
                    $existing->metadata = $metadata;
                }
            }
        }

        $existing->save();

        if ($existing->status !== $originalStatus) {
            if ($existing->status === 'deleted') {
                $this->broadcastService->emitMessageDelete([
                    'message_id' => (string) $existing->id,
                    'ticket_id' => (string) $existing->ticket_id,
                    'tenant_id' => $tenantId,
                    'status' => 'deleted',
                    'deleted_at' => $existing->deleted_at?->toIso8601String(),
                    'deleted_by' => $existing->deleted_by,
                ]);

                return true;
            }

            $this->broadcastService->emitMessageStatus([
                'message_id' => (string) $existing->id,
                'ticket_id' => (string) $existing->ticket_id,
                'tenant_id' => $tenantId,
                'status' => $existing->status,
                'sent_at' => $existing->sent_at?->toIso8601String(),
                'delivered_at' => $existing->delivered_at?->toIso8601String(),
                'read_at' => $existing->read_at?->toIso8601String(),
            ]);
        }

        return true;
    }
}
