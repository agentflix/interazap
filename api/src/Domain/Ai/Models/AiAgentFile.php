<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Auth\Models\AuthUser;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Arquivo de contexto vinculado a um Agente de IA.
 *
 * Armazena conteúdo configurável (IDENTITY.md, SOUL.md, PLAYBOOK.md, etc.)
 * que o agente recebe como parte do payload de execução ao gateway.
 * Cada arquivo é identificado pelo slug e injetado no prompt do agente.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $agent_id
 * @property string $slug Identificador do arquivo (ex.: IDENTITY, SOUL).
 * @property string $content Conteúdo em texto/markdown do arquivo.
 * @property string|null $updated_by UUID do usuário que fez a última atualização.
 */
class AiAgentFile extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_agent_files';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'agent_id',
        'slug',
        'content',
        'updated_by',
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
     * Agente ao qual este arquivo pertence.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }

    /**
     * Usuário que realizou a última atualização do conteúdo.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'updated_by');
    }
}
