<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiAutopilotRunFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Registro de Execução (Run) do Autopilot.
 *
 * Armazena o estado, logs, contexto de entrada e resultado final de uma
 * execução de playbook ou tarefa autônoma da IA.
 *
 * @category Models
 */
class AiAutopilotRun extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_autopilot_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'playbook_id',
        'parent_run_id',
        'delegation_depth',
        'cached_prompt_tokens',
        'streaming_enabled',
        'classifier_result',
        'classifier_tokens',
        'status',
        'playbook_version',
        'input_context',
        'output',
        'started_at',
        'completed_at',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'input_context' => 'array',
        'output' => 'array',
        'delegation_depth' => 'integer',
        'cached_prompt_tokens' => 'integer',
        'streaming_enabled' => 'boolean',
        'classifier_tokens' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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
            // Only auto-set started_at for non-queued runs
            // Queued runs have started_at set by the job when processing begins
            if (! $item->started_at && $item->status !== 'queued') {
                $item->started_at = now();
            }
        });
    }

    protected static function newFactory(): AiAutopilotRunFactory
    {
        return AiAutopilotRunFactory::new();
    }

    public function parentRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_run_id');
    }
}
