<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Habilidade (skill/função) habilitada para um Agente de IA.
 *
 * Representa uma capacidade específica que o agente pode exercer
 * durante a execução de uma conversa, complementando as ferramentas
 * configuradas via AiAutopilotTool.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $agent_id
 * @property string $name Nome da habilidade.
 * @property string|null $description Descrição do que a habilidade faz.
 * @property bool $is_active
 * @property array|null $metadata
 */
class AiAgentSkill extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_agent_skills';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'agent_id',
        'name',
        'description',
        'is_active',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
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
     * Agente ao qual esta habilidade pertence.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }
}
