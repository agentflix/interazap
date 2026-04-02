<?php

declare(strict_types=1);

namespace Domain\Billing\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento de webhook de billing recebido do Gateway.
 *
 * Proxy sobre shared_webhook_events filtrado por domain = 'billing'.
 * Modelo append-only otimizado para gravação rápida de eventos do provider de
 * pagamento (Stripe, Pagar.me etc), garantindo rastreabilidade e idempotência.
 *
 * @category Models
 */
class BillingWebhookEvent extends Model
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
        'provider_event_id',
        'event_type',
        'payload',
        'payload_hash',
        'payload_json',
        'processed_at',
        'received_at',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'payload_json' => 'array',
        'processed_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'domain' => 'billing',
    ];

    /**
     * Escopo global para filtrar somente eventos de billing.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('billing', function ($query): void {
            $query->where('domain', 'billing');
        });

        static::creating(function (self $event): void {
            $event->domain = 'billing';
        });
    }
}
