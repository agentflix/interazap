<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Modelo de Skill de Agente em Fila de Roteamento.
 *
 * Representa uma habilidade/skill vinculada a um agente em uma fila de roteamento.
 * Usada na estratégia skill_based para match com o campo category do ticket.
 *
 * @property string $id
 * @property string $queue_id
 * @property string $user_id
 * @property string $skill
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @category Models
 */
class ChatRoutingAgentSkill extends Model
{
    protected $table = 'chat_routing_agent_skills';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'queue_id',
        'user_id',
        'skill',
    ];

    /**
     * Inicializar o modelo e definir comportamentos automáticos.
     */
    protected static function booted(): void
    {
        static::creating(function (self $skill): void {
            if (! $skill->id) {
                $skill->id = (string) Str::orderedUuid();
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
     * Relacionamento com o Agente da fila.
     *
     * @return BelongsTo<ChatRoutingQueueAgent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(ChatRoutingQueueAgent::class, 'user_id', 'user_id');
    }
}
