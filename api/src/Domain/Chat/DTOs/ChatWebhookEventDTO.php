<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Support\Arr;

/**
 * DTO for Chat Webhook Event.
 *
 * @readonly
 */
final readonly class ChatWebhookEventDTO
{
    /**
     * @param  string  $provider  Provider name (e.g. uazapi).
     * @param  string|null  $eventType  Event type (messages, connection, etc).
     * @param  string  $instanceWebhookToken  Instance webhook token.
     * @param  string|null  $tenantId  Resolved tenant identifier.
     * @param  string|null  $instanceId  Resolved instance identifier.
     * @param  string|null  $direction  Event direction (incoming/outgoing).
     * @param  array<string, mixed>|null  $message  Normalized message payload.
     * @param  array<string, mixed>|null  $chat  Normalized chat payload.
     * @param  array<string, mixed>  $raw  Raw received payload.
     * @param  string|null  $owner  Owner identifier on gateway.
     * @param  string|null  $baseUrl  Base URL reported by gateway.
     */
    public function __construct(
        public string $provider,
        public ?string $eventType,
        public string $instanceWebhookToken,
        public ?string $tenantId,
        public ?string $instanceId,
        public ?string $direction,
        public ?array $message,
        public ?array $chat,
        public array $raw,
        public ?string $owner,
        public ?string $baseUrl,
    ) {}

    /**
     * Build DTO from normalized payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromNormalized(array $payload): self
    {
        return new self(
            provider: (string) ($payload['provider'] ?? 'uazapi'),
            eventType: Arr::get($payload, 'event_type'),
            instanceWebhookToken: (string) ($payload['instance_webhook_token'] ?? ''),
            tenantId: Arr::get($payload, 'tenant_id'),
            instanceId: Arr::get($payload, 'instance_id'),
            direction: Arr::get($payload, 'direction'),
            message: Arr::get($payload, 'message'),
            chat: Arr::get($payload, 'chat'),
            raw: (array) ($payload['raw'] ?? []),
            owner: Arr::get($payload, 'owner'),
            baseUrl: Arr::get($payload, 'base_url'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'event_type' => $this->eventType,
            'instance_webhook_token' => $this->instanceWebhookToken,
            'tenant_id' => $this->tenantId,
            'instance_id' => $this->instanceId,
            'direction' => $this->direction,
            'message' => $this->message,
            'chat' => $this->chat,
            'owner' => $this->owner,
            'base_url' => $this->baseUrl,
            'raw' => $this->raw,
        ];
    }
}
