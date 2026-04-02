<?php

declare(strict_types=1);

namespace Domain\Shared\Services;

use Illuminate\Support\Facades\Log;

/**
 * Structured logging for business events.
 *
 * All business events are logged in JSON format for analysis and auditing.
 * Sensitive data is automatically filtered.
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
     * Log a ticket event.
     *
     * @param  array<string, mixed>  $context
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
     * Log a ticket closed event.
     *
     * @param  array<string, mixed>  $context
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
     * Log a message sent event.
     *
     * @param  array<string, mixed>  $context
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
     * Log a message received event.
     *
     * @param  array<string, mixed>  $context
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
     * Log a negotiation event.
     *
     * @param  array<string, mixed>  $context
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
     * Log a negotiation won event.
     *
     * @param  array<string, mixed>  $context
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
     * Log a negotiation lost event.
     *
     * @param  array<string, mixed>  $context
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
     * Log a payment received event.
     *
     * @param  array<string, mixed>  $context
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
     * Log a security event.
     *
     * @param  array<string, mixed>  $context
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
     * Log a generic business event.
     *
     * @param  array<string, mixed>  $context
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
     * Remove sensitive data from context.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
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
