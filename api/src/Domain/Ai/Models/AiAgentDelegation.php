<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Regra de delegação entre dois Agentes de IA.
 *
 * Define a configuração de encaminhamento de uma conversa do agente origem
 * para o agente destino, incluindo profundidade máxima de delegação para
 * evitar loops infinitos em cadeias multi-agente.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $source_agent_id UUID do agente que delega.
 * @property string $target_agent_id UUID do agente que recebe a delegação.
 * @property int $max_depth Profundidade máxima de delegações encadeadas.
 * @property bool $is_active
 * @property array|null $metadata
 */
class AiAgentDelegation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_agent_delegations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'source_agent_id',
        'target_agent_id',
        'max_depth',
        'is_active',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'max_depth' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->id) {
                $item->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Agente de origem da delegação.
     */
    public function sourceAgent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'source_agent_id');
    }

    /**
     * Agente destino da delegação.
     */
    public function targetAgent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'target_agent_id');
    }
}
