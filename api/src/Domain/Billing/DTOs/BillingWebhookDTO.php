<?php

declare(strict_types=1);

namespace Domain\Billing\DTOs;

/**
 * DTO for Asaas webhook input.
 *
 * @readonly
 */
final readonly class BillingWebhookDTO
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $eventType,
        public readonly ?string $providerEventId,
        public readonly string $payloadHash,
        public readonly string $idempotencyKey,
        public readonly array $payload,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'event_type' => $this->eventType,
            'provider_event_id' => $this->providerEventId,
            'payload_hash' => $this->payloadHash,
            'idempotency_key' => $this->idempotencyKey,
            'payload' => $this->payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $eventType = strtoupper((string) ($payload['event_type'] ?? $payload['event'] ?? 'UNKNOWN'));
        $provider = strtoupper((string) ($payload['provider'] ?? 'ASAAS'));
        $payment = (array) ($payload['payment'] ?? $payload['payload']['payment'] ?? []);
        $providerEventId = $payload['provider_event_id'] ?? $payment['id'] ?? null;
        $payloadBody = (array) ($payload['payload'] ?? $payload);
        $payloadHash = (string) ($payload['payload_hash'] ?? self::hashPayload($payloadBody));
        $idempotencyKey = self::computeIdempotency($providerEventId, $eventType, $payloadHash);

        return new self(
            provider: $provider,
            eventType: $eventType,
            providerEventId: $providerEventId,
            payloadHash: $payloadHash,
            idempotencyKey: $idempotencyKey,
            payload: $payloadBody,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function hashPayload(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private static function computeIdempotency(?string $providerEventId, string $eventType, string $payloadHash): string
    {
        if ($providerEventId) {
            return hash('sha256', 'asaas|'.$eventType.'|'.$providerEventId);
        }

        return hash('sha256', 'asaas|'.$eventType.'|'.$payloadHash);
    }
}
