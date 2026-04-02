<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de Evento de Webhook de Chat.
 *
 * Proxy sobre shared_webhook_events filtrado por domain = 'chat'.
 * Registra todos os payloads brutos recebidos dos gateways de mensageria.
 * Funciona como um log imutável (append-only) para auditoria e reprocessamento,
 * garantindo idempotência via chave única.
 *
 * @property string $id Identificador UUID.
 * @property string|null $tenant_id Identificador do tenant resolvido.
 * @property string $domain Dominio fixo 'chat'.
 * @property string|null $stream_id Identificador do stream original no gateway.
 * @property string|null $idempotency_key Chave única para evitar duplicidade de processamento.
 * @property string $provider Nome do provedor (ex: uazapi).
 * @property string|null $instance_webhook_token Token da instância que recebeu o evento.
 * @property string $event_type Tipo do evento (message, presence, status, etc).
 * @property string|null $direction Direção do evento (inbound/outbound).
 * @property array $payload Conteúdo completo em formato JSON.
 * @property \Illuminate\Support\Carbon|null $processed_at Data e hora em que o processamento foi concluído.
 * @property \Illuminate\Support\Carbon $created_at Data de recebimento.
 * @property \Illuminate\Support\Carbon $updated_at Data de atualização.
 *
 * @category Models
 */
class ChatWebhookEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;

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
        'event_type',
        'direction',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'domain' => 'chat',
    ];

    /**
     * Escopo global para filtrar somente eventos de chat.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('chat', function ($query): void {
            $query->where('domain', 'chat');
        });

        static::creating(function (self $event): void {
            $event->domain = 'chat';
        });
    }
}
