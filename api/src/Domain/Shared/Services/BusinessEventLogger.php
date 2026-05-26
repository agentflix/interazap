<?php

declare(strict_types=1);

namespace Domain\Shared\Services;

use Illuminate\Support\Facades\Log;

/**
 * Logger estruturado para eventos de negócio.
 *
 * Registra todos os eventos de negócio em formato JSON para análise e auditoria.
 * Dados sensíveis (senhas, tokens, CPF, CNPJ etc.) são filtrados automaticamente.
 */
final class BusinessEventLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'token',
        'secret',
        'api_key',
        'credit_card',
        'cpf',
        'cnpj',
    ];

    /**
     * Registra o evento de criação de ticket.
     *
     * @param  string  $ticketId  Identificador do ticket criado.
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $context  Contexto adicional do evento.
     */
    public function ticketCreated(string $ticketId, string $tenantId, array $context = []): void
    {
        $this->logBusinessEvent('ticket.created', [
            'ticket_id' => $ticketId,
            'tenant_id' => $tenantId,
            ...$context,
        ]);
    }

    /**
     * Registra o evento de fechamento de ticket.
     *
     * @param  string  $ticketId  Identificador do ticket.
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $status  Status final do ticket.
     * @param  array<string, mixed>  $context  Contexto adicional do evento.
     */
    public function ticketClosed(string $ticketId, string $tenantId, string $status, array $context = []): void
    {
        $this->logBusinessEvent('ticket.closed', [
            'ticket_id' => $ticketId,
            'tenant_id' => $tenantId,
            'status' => $status,
            ...$context,
        ]);
    }

    /**
     * Registra o evento de mensagem enviada.
     *
     * @param  string  $messageId  Identificador da mensagem.
     * @param  string  $ticketId  Identificador do ticket relacionado.
     * @param  string  $direction  Direção da mensagem ('incoming' ou 'outgoing').
     * @param  array<string, mixed>  $context  Contexto adicional do evento.
     */
    public function messageSent(string $messageId, string $ticketId, string $direction, array $context = []): void
    {
        $this->logBusinessEvent('message.sent', [
            'message_id' => $messageId,
            'ticket_id' => $ticketId,
            'direction' => $direction,
            ...$context,
        ]);
    }

    /**
     * Registra o evento de mensagem recebida.
     *
     * @param  string  $messageId  Identificador da mensagem.
     * @param  string  $ticketId  Identificador do ticket relacionado.
     * @param  string  $channel  Canal de origem da mensagem.
     * @param  array<string, mixed>  $context  Contexto adicional do evento.
     */
    public function messageReceived(string $messageId, string $ticketId, string $channel, array $context = []): void
    {
        $this->logBusinessEvent('message.received', [
            'message_id' => $messageId,
            'ticket_id' => $ticketId,
            'channel' => $channel,
            ...$context,
        ]);
    }

    /**
     * Registra o evento de atualização de negociação.
     *
     * @param  string  $negotiationId  Identificador da negociação.
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $status  Novo status da negociação.
     * @param  float  $value  Valor monetário da negociação.
     * @param  array<string, mixed>  $context  Contexto adicional do evento.
     */
    public function negotiationUpdated(string $negotiationId, string $tenantId, string $status, float $value, array $context = []): void
    {
        $this->logBusinessEvent('negotiation.updated', [
            'negotiation_id' => $negotiationId,
            'tenant_id' => $tenantId,
            'status' => $status,
            'value' => $value,
            ...$context,
        ]);
    }

    /**
     * Registra o evento de negociação ganha.
     *
     * @param  string  $negotiationId  Identificador da negociação.
     * @param  string  $tenantId  Identificador do tenant.
     * @param  float  $value  Valor monetário da negociação.
     * @param  array<string, mixed>  $context  Contexto adicional do evento.
     */
    public function negotiationWon(string $negotiationId, string $tenantId, float $value, array $context = []): void
    {
        $this->logBusinessEvent('negotiation.won', [
            'negotiation_id' => $negotiationId,
            'tenant_id' => $tenantId,
            'value' => $value,
            ...$context,
        ]);
    }

    /**
     * Registra o evento de negociação perdida.
     *
     * @param  string  $negotiationId  Identificador da negociação.
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $reason  Motivo da perda da negociação.
     * @param  array<string, mixed>  $context  Contexto adicional do evento.
     */
    public function negotiationLost(string $negotiationId, string $tenantId, string $reason, array $context = []): void
    {
        $this->logBusinessEvent('negotiation.lost', [
            'negotiation_id' => $negotiationId,
            'tenant_id' => $tenantId,
            'reason' => $reason,
            ...$context,
        ]);
    }

    /**
     * Registra o evento de pagamento recebido.
     *
     * @param  string  $paymentId  Identificador do pagamento.
     * @param  string  $tenantId  Identificador do tenant.
     * @param  float  $amount  Valor do pagamento.
     * @param  string  $method  Método de pagamento utilizado.
     * @param  array<string, mixed>  $context  Contexto adicional do evento.
     */
    public function paymentReceived(string $paymentId, string $tenantId, float $amount, string $method, array $context = []): void
    {
        $this->logBusinessEvent('payment.received', [
            'payment_id' => $paymentId,
            'tenant_id' => $tenantId,
            'amount' => $amount,
            'method' => $method,
            ...$context,
        ]);
    }

    /**
     * Registra um evento de segurança no canal dedicado.
     *
     * @param  string  $event  Nome do evento de segurança.
     * @param  string  $userId  Identificador do usuário envolvido.
     * @param  array<string, mixed>  $context  Contexto adicional do evento.
     */
    public function securityEvent(string $event, string $userId, array $context = []): void
    {
        Log::channel('security')->info($event, $this->sanitize([
            'user_id' => $userId,
            'event' => $event,
            'timestamp' => now()->toISOString(),
            ...$context,
        ]));
    }

    /**
     * Registra um evento genérico de negócio no canal JSON.
     *
     * @param  string  $event  Nome do evento.
     * @param  array<string, mixed>  $context  Contexto sanitizado do evento.
     */
    private function logBusinessEvent(string $event, array $context): void
    {
        Log::channel('json')->info($event, $this->sanitize([
            'event' => $event,
            'timestamp' => now()->toISOString(),
            ...$context,
        ]));
    }

    /**
     * Remove dados sensíveis do contexto substituindo-os por '[REDACTED]'.
     *
     * @param  array<string, mixed>  $context  Contexto original do evento.
     * @return array<string, mixed> Contexto com campos sensíveis mascarados.
     */
    private function sanitize(array $context): array
    {
        $result = [];

        foreach ($context as $key => $value) {
            $lowerKey = strtolower((string) $key);

            // Check if key contains sensitive data
            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (str_contains($lowerKey, $sensitiveKey)) {
                    $result[$key] = '[REDACTED]';

                    continue 2;
                }
            }

            // Recursively sanitize arrays
            if (is_array($value)) {
                $result[$key] = $this->sanitize($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
