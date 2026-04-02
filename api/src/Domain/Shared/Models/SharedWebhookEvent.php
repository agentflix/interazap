<?php

declare(strict_types=1);

namespace Domain\Shared\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Evento de webhook unificado.
 *
 * Consolida os eventos de webhook de chat e billing em uma unica tabela,
 * diferenciados pelo discriminador domain. Garante rastreabilidade e
 * idempotencia via chaves unicas por dominio.
 *
 * @property string $id Identificador UUID.
 * @property string|null $tenant_id Identificador do tenant.
 * @property string $domain Dominio do evento (chat, billing).
 * @property string|null $stream_id Identificador do stream original.
 * @property string|null $idempotency_key Chave de idempotencia.
 * @property string $provider Nome do provedor.
 * @property string $instance_webhook_token Token da instancia.
 * @property string|null $provider_event_id ID do evento no provedor.
 * @property string|null $event_type Tipo do evento.
 * @property string|null $direction Direcao (inbound/outbound).
 * @property array $payload Conteudo completo.
 * @property string|null $payload_hash Hash do payload.
 * @property array|null $payload_json Payload parseado.
 * @property \Illuminate\Support\Carbon|null $received_at Data de recebimento.
 * @property \Illuminate\Support\Carbon|null $processed_at Data de processamento.
 *
 * @category Models
 */
class SharedWebhookEvent extends Model
{
    use BelongsToTenant;

    protected $table = 'shared_webhook_events';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'domain',
        'stream_id',
        'idempotency_key',
        'provider',
        'instance_webhook_token',
        'provider_event_id',
        'event_type',
        'direction',
        'payload',
        'payload_hash',
        'payload_json',
        'received_at',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'payload_json' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Inicializar o modelo e definir comportamentos automaticos.
     */
    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (! $event->id) {
                $event->id = (string) Str::orderedUuid();
            }
        });
    }
}
