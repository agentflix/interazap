<?php

declare(strict_types=1);

namespace Domain\Gateway\DTOs;

use Domain\Gateway\Enums\GatewayDomain;
use Illuminate\Support\Str;

/**
 * DTO imutável que representa uma mensagem enviada ao gateway NestJS via Redis Stream.
 *
 * Encapsula domínio, ação, provider, payload e metadados com correlationId
 * único para rastreamento da resposta assíncrona.
 */
final readonly class GatewayMessage
{
    /**
     * @param  string  $correlationId  Identificador único para correlacionar a resposta
     * @param  string  $timestamp  Data/hora ISO-8601 do envio
     * @param  GatewayDomain  $domain  Domínio de destino (AI, WhatsApp, Payment)
     * @param  string  $action  Ação a executar (ex: complete, send-message)
     * @param  string  $provider  Provider de destino (ex: openai, zapi)
     * @param  array<string, mixed>  $payload  Dados da operação
     * @param  array<string, mixed>  $metadata  Metadados extras para rastreamento
     */
    public function __construct(
        public string $correlationId,
        public string $timestamp,
        public GatewayDomain $domain,
        public string $action,
        public string $provider,
        public array $payload,
        public array $metadata = [],
    ) {}

    /**
     * Cria uma nova GatewayMessage com correlationId e timestamp gerados automaticamente.
     *
     * @param  array<string, mixed>  $payload  Dados da operação
     * @param  array<string, mixed>  $metadata  Metadados extras
     * @param  string|null  $correlationId  ID customizado; se nulo, gera UUID ordenado
     */
    public static function create(
        GatewayDomain $domain,
        string $action,
        string $provider,
        array $payload,
        array $metadata = [],
        ?string $correlationId = null,
    ): self {
        return new self(
            correlationId: $correlationId ?? (string) Str::orderedUuid(),
            timestamp: now()->toIso8601String(),
            domain: $domain,
            action: $action,
            provider: $provider,
            payload: $payload,
            metadata: $metadata,
        );
    }

    /**
     * Converte a mensagem em array para publicação no Redis Stream.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'correlationId' => $this->correlationId,
            'timestamp' => $this->timestamp,
            'domain' => $this->domain->value,
            'action' => $this->action,
            'provider' => $this->provider,
            'payload' => json_encode($this->payload, JSON_THROW_ON_ERROR),
            'metadata' => json_encode($this->metadata, JSON_THROW_ON_ERROR),
        ];
    }
}
