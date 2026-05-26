<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiAgentFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Agente de IA configurável por tenant.
 *
 * Representa um agente autônomo com papel, modelo LLM, prompt de sistema,
 * limites de tokens e configurações de voz (STT/TTS). Pode ter gatilhos,
 * ferramentas, canais, arquivos de contexto (IDENTITY.md, SOUL.md, etc.)
 * e regras de delegação para outros agentes.
 *
 * @category Models
 */
class AiAgent extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_agents';

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
        'type',
        'model_id',
        'system_prompt',
        'max_tokens',
        'temperature',
        'top_p',
        'is_active',
        'parent_agent_id',
        'classifier_model',
        'token_budget_input',
        'token_budget_output',
        'fallback_message',
        'metadata',
        'voice_response_mode',
        'stt_model',
        'stt_language',
        'tts_model',
        'tts_voice',
        'tts_speed',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'max_tokens' => 'integer',
        'temperature' => 'float',
        'top_p' => 'float',
        'is_active' => 'boolean',
        'token_budget_input' => 'integer',
        'token_budget_output' => 'integer',
        'metadata' => 'array',
        'tts_speed' => 'float',
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
     * Agente pai (para hierarquia de delegação).
     */
    public function parentAgent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_agent_id');
    }

    /**
     * Arquivos de contexto vinculados ao agente (IDENTITY.md, SOUL.md, etc.).
     */
    public function files(): HasMany
    {
        return $this->hasMany(AiAgentFile::class, 'agent_id');
    }

    /**
     * Gatilhos configurados para ativar o agente.
     */
    public function triggers(): HasMany
    {
        return $this->hasMany(AiAgentTrigger::class, 'agent_id');
    }

    /**
     * Habilidades (skills/funções) habilitadas para o agente.
     */
    public function skills(): HasMany
    {
        return $this->hasMany(AiAgentSkill::class, 'agent_id');
    }

    /**
     * Canais de comunicação vinculados ao agente.
     */
    public function channels(): HasMany
    {
        return $this->hasMany(AiAgentChannel::class, 'agent_id');
    }

    /**
     * Delegações em que este agente é a origem.
     */
    public function sourceDelegations(): HasMany
    {
        return $this->hasMany(AiAgentDelegation::class, 'source_agent_id');
    }

    /**
     * Delegações em que este agente é o destino.
     */
    public function targetDelegations(): HasMany
    {
        return $this->hasMany(AiAgentDelegation::class, 'target_agent_id');
    }

    /**
     * Tools vinculadas a este agente via tabela pivot ai_agent_tools.
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(
            AiAutopilotTool::class,
            'ai_agent_tools',
            'agent_id',
            'tool_id',
        )->withTimestamps();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): AiAgentFactory
    {
        return AiAgentFactory::new();
    }
}
