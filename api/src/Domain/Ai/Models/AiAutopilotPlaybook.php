<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiAutopilotPlaybookFactory;
use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Playbook de Automação IA.
 *
 * Define um fluxo estruturado de tarefas e passos que o agente de IA deve seguir,
 * atuando como um "roteiro" para execução de processos complexos.
 *
 * @category Models
 */
class AiAutopilotPlaybook extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_autopilot_playbooks';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'description',
        'trigger_type',
        'version',
        'steps',
        'metadata',
        'is_active',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'trigger_type' => AutopilotTriggerType::class,
        'steps' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Inicializar o modelo e definir comportamentos automáticos.
     */
    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->id) {
                $item->id = (string) Str::orderedUuid();
            }
        });
    }

    protected static function newFactory(): AiAutopilotPlaybookFactory
    {
        return AiAutopilotPlaybookFactory::new();
    }
}
