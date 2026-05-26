<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Support\Arr;

/**
 * DTO de Evento de Webhook de Chat.
 *
 * @readonly
 */
final readonly class ChatWebhookEventDTO
{
    /**
     * @param  string  $provider  Nome do provedor (ex.: uazapi).
     * @param  string|null  $eventType  Tipo do evento (messages, connection, etc.).
     * @param  string  $instanceWebhookToken  Token de webhook da instância.
     * @param  string|null  $tenantId  Identificador do tenant resolvido.
     * @param  string|null  $instanceId  Identificador da instância resolvida.
     * @param  string|null  $direction  Direção do evento (incoming/outgoing).
     * @param  array<string, mixed>|null  $message  Payload da mensagem normalizada.
     * @param  array<string, mixed>|null  $chat  Payload do chat normalizado.
     * @param  array<string, mixed>  $raw  Payload bruto recebido.
     * @param  string|null  $owner  Identificador do proprietário no gateway.
     * @param  string|null  $baseUrl  URL base informada pelo gateway.
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
     * Constrói o DTO a partir de um payload normalizado.
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
