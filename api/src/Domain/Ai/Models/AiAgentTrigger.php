<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Configuração de gatilho para ativação de um Agente de IA.
 *
 * Define as condições que disparam a execução de um agente em uma conversa,
 * como mensagem recebida, alteração de etapa, criação de ticket ou cron.
 * O campo config armazena parâmetros específicos do tipo (ex.: expressão cron).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $agent_id
 * @property string $type Tipo do gatilho (AutopilotTriggerType value).
 * @property array|null $config Configuração específica do tipo de gatilho.
 * @property string $status Status do gatilho ('active' ou 'inactive').
 * @property \Illuminate\Support\Carbon|null $last_run_at Última execução do gatilho.
 */
class AiAgentTrigger extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_agent_triggers';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'agent_id',
        'type',
        'config',
        'status',
        'last_run_at',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'config' => 'array',
        'last_run_at' => 'datetime',
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
     * Agente associado a este gatilho.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }
}
