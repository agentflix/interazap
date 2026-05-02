<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Modelo de Fila de Roteamento de Atendimentos.
 *
 * Representa uma fila de distribuição automática de tickets entre agentes,
 * podendo estar vinculada a uma instância específica (canal) ou ser global.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $instance_id
 * @property string $name
 * @property bool $is_enabled
 * @property string $strategy
 * @property int|null $max_open_tickets_per_agent
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @category Models
 */
class ChatRoutingQueue extends Model
{
    use BelongsToTenant;

    protected $table = 'chat_routing_queues';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'instance_id',
        'name',
        'is_enabled',
        'strategy',
        'max_open_tickets_per_agent',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'strategy' => 'string',
        'max_open_tickets_per_agent' => 'integer',
    ];

    /**
     * Inicializar o modelo e definir comportamentos automáticos.
     */
    protected static function booted(): void
    {
        static::creating(function (self $queue): void {
            if (! $queue->id) {
                $queue->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Relacionamento com os agentes vinculados à fila.
     *
     * @return HasMany<ChatRoutingQueueAgent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(ChatRoutingQueueAgent::class, 'queue_id');
    }

    /**
     * Relacionamento com a Instância de conexão (Canal).
     *
     * @return BelongsTo<ChatInstance, $this>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(ChatInstance::class, 'instance_id');
    }

    /**
     * Scope: Filtrar filas por instância (canal).
     *
     * @param  Builder<self>  $query
     */
    public function scopeForInstance(Builder $query, string $instanceId): void
    {
        $query->where('instance_id', $instanceId);
    }

    /**
     * Scope: Filtrar apenas filas globais (sem instância vinculada).
     *
     * @param  Builder<self>  $query
     */
    public function scopeGlobal(Builder $query): void
    {
        $query->whereNull('instance_id');
    }
}
