<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Solicitação de Aprovação Humana (Human-in-the-loop).
 *
 * Registro gerado quando uma ação do Autopilot é interceptada por um Guardrail
 * e requer validação manual de um supervisor antes de prosseguir.
 *
 * @category Models
 */
final class AiAutopilotApproval extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_autopilot_approvals';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'run_id',
        'status',
        'requested_action',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejected_reason',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'requested_action' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Inicializar o modelo e definir comportamentos automáticos.
     */
    protected static function booted(): void
    {
        self::creating(function (self $item): void {
            if (! $item->id) {
                $item->id = (string) Str::orderedUuid();
            }
        });
    }
}
