<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Domain\Auth\Models\AuthUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Modelo de Agente em Fila de Roteamento.
 *
 * Representa a vinculação de um usuário/agente a uma fila de roteamento,
 * controlando sua posição no rodízio, última atribuição e estado ativo.
 *
 * @property string $id
 * @property string $queue_id
 * @property string $user_id
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $last_assigned_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @category Models
 */
class ChatRoutingQueueAgent extends Model
{
    protected $table = 'chat_routing_queue_agents';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'queue_id',
        'user_id',
        'position',
        'last_assigned_at',
        'is_active',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_assigned_at' => 'datetime',
        'position' => 'integer',
    ];

    /**
     * Inicializar o modelo e definir comportamentos automáticos.
     */
    protected static function booted(): void
    {
        static::creating(function (self $agent): void {
            if (! $agent->id) {
                $agent->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Relacionamento com a Fila de roteamento.
     *
     * @return BelongsTo<ChatRoutingQueue, $this>
     */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(ChatRoutingQueue::class, 'queue_id');
    }

    /**
     * Relacionamento com o Usuário (Agente).
     *
     * @return BelongsTo<AuthUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id');
    }
}
