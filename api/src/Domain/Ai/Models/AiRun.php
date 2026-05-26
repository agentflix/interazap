<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Registro de execução de uma completion de IA (modelo genérico).
 *
 * Rastreia o status e resultado de requisições de completion processadas
 * de forma assíncrona pelo ProcessAIResponseJob. Diferente de AiAutopilotRun,
 * este modelo representa execuções simples sem playbook ou contexto multi-turno.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $status Status da execução (pending/processing/retrying/completed/failed).
 * @property string $prompt Prompt enviado ao modelo.
 * @property string|null $output Resposta gerada pelo modelo.
 * @property string|null $model Nome do modelo utilizado.
 * @property int|null $tokens_used Total de tokens consumidos.
 * @property string|null $finish_reason Razão de parada do modelo.
 * @property string|null $error Mensagem de erro em caso de falha.
 * @property array|null $metadata Metadados adicionais da execução.
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AiRun extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'status',
        'prompt',
        'output',
        'model',
        'tokens_used',
        'finish_reason',
        'error',
        'metadata',
        'started_at',
        'completed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tokens_used' => 'integer',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * Inicializa o modelo com UUID automático e status padrão PENDING.
     */
    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            if (! $run->id) {
                $run->id = (string) Str::orderedUuid();
            }
            if (! $run->status) {
                $run->status = self::STATUS_PENDING;
            }
        });
    }

    /**
     * Verifica se a execução está pendente.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Verifica se a execução está em processamento.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Verifica se a execução foi concluída com sucesso.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Verifica se a execução falhou.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
