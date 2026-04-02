<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Regra de Segurança (Guardrail) para IA.
 *
 * Define restrições e políticas que monitoram as ações do Autopilot,
 * podendo bloquear, alertar ou exigir aprovação humana (HITL).
 *
 * @category Models
 */
class AiAutopilotGuardrail extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_autopilot_guardrails';

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
        'rule_type',
        'conditions',
        'priority',
        'is_active',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'conditions' => 'array',
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
}
