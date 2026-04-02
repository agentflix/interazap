<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

/**
 * Emite eventos explícitos de ciclo de vida da IA no contrato unificado chat.activity.
 */
final readonly class ChatAiActivityService
{
    public function __construct(
        private ChatActivityBroadcastService $activityBroadcast,
    ) {}

    /**
     * @param  array<string, mixed>  $extra
     */
    public function emitProcessingStarted(string $tenantId, string $ticketId, string $runId, string $messageId = '', array $extra = []): void
    {
        $this->emit($tenantId, $ticketId, 'ai.processing.started', [
            'tenant_id' => $tenantId,
            'ticket_id' => $ticketId,
            'run_id' => $runId,
            'message_id' => $messageId,
            'status' => 'queued',
            'requested_at' => now()->toIso8601String(),
            ...$extra,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function emitProcessingCompleted(string $tenantId, string $ticketId, string $runId, string $messageId = '', array $extra = []): void
    {
        $this->emit($tenantId, $ticketId, 'ai.processing.completed', [
            'tenant_id' => $tenantId,
            'ticket_id' => $ticketId,
            'run_id' => $runId,
            'message_id' => $messageId,
            'status' => 'completed',
            'completed_at' => now()->toIso8601String(),
            ...$extra,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function emitProcessingFailed(string $tenantId, string $ticketId, string $runId, ?string $error = null, string $messageId = '', array $extra = []): void
    {
        $this->emit($tenantId, $ticketId, 'ai.processing.failed', [
            'tenant_id' => $tenantId,
            'ticket_id' => $ticketId,
            'run_id' => $runId,
            'message_id' => $messageId,
            'status' => 'failed',
            'error' => $error,
            'completed_at' => now()->toIso8601String(),
            ...$extra,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function emitProcessingRejected(string $tenantId, string $ticketId, ?string $runId = null, ?string $reason = null, string $messageId = '', array $extra = []): void
    {
        $this->emit($tenantId, $ticketId, 'ai.processing.rejected', [
            'tenant_id' => $tenantId,
            'ticket_id' => $ticketId,
            'run_id' => $runId,
            'message_id' => $messageId,
            'status' => 'rejected',
            'reason' => $reason,
            'completed_at' => now()->toIso8601String(),
            ...$extra,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function emit(string $tenantId, string $ticketId, string $type, array $data): void
    {
        if ($ticketId === '') {
            return;
        }

        $this->activityBroadcast->emit($ticketId, [[
            'type' => $type,
            'data' => $data,
        ]], $tenantId);
    }
}
