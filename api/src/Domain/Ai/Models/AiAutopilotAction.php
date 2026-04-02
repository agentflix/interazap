<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Ação executada pelo Autopilot.
 *
 * Representa um passo individual realizado pelo agente de IA durante um Run,
 * incluindo o input enviado para a ferramenta e o output/resultado obtido.
 *
 * @category Models
 */
class AiAutopilotAction extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_autopilot_actions';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'run_id',
        'action_type',
        'input',
        'output',
        'guardrail_result',
        'order',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'input' => 'array',
        'output' => 'array',
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
