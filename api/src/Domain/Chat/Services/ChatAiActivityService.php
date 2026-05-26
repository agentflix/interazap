<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

/**
 * Emite eventos explícitos de ciclo de vida da IA no contrato unificado chat.activity.
 *
 * Publica eventos WebSocket de estado da execução da IA (iniciado, concluído,
 * falhou, rejeitado) via ChatActivityBroadcastService para que o frontend
 * exiba indicadores de progresso em tempo real.
 *
 * @category Services
 */
final readonly class ChatAiActivityService
{
    public function __construct(
        private ChatActivityBroadcastService $activityBroadcast,
    ) {}

    /**
     * Emite evento de início do processamento da IA.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket.
     * @param  string  $runId  Identificador da execução IA.
     * @param  string  $messageId  Identificador da mensagem que disparou o processamento.
     * @param  array<string, mixed>  $extra  Dados adicionais a mesclar no payload.
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
     * Emite evento de conclusão bem-sucedida do processamento da IA.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket.
     * @param  string  $runId  Identificador da execução IA.
     * @param  string  $messageId  Identificador da mensagem processada.
     * @param  array<string, mixed>  $extra  Dados adicionais a mesclar no payload.
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
     * Emite evento de falha no processamento da IA.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket.
     * @param  string  $runId  Identificador da execução IA.
     * @param  string|null  $error  Mensagem de erro capturada.
     * @param  string  $messageId  Identificador da mensagem processada.
     * @param  array<string, mixed>  $extra  Dados adicionais a mesclar no payload.
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
     * Emite evento de rejeição do processamento da IA (ex.: cota excedida, fora do horário).
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket.
     * @param  string|null  $runId  Identificador da execução IA, se disponível.
     * @param  string|null  $reason  Motivo da rejeição.
     * @param  string  $messageId  Identificador da mensagem processada.
     * @param  array<string, mixed>  $extra  Dados adicionais a mesclar no payload.
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
     * Publica um evento de atividade via ChatActivityBroadcastService.
     *
     * Descarta silenciosamente se ticketId for vazio.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket.
     * @param  string  $type  Tipo do evento (ex.: 'ai.processing.started').
     * @param  array<string, mixed>  $data  Payload do evento.
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
