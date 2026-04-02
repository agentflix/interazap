<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Ferramenta (Tool) disponível para o Autopilot.
 *
 * Define uma capacidade funcional (função) que pode ser invocada pelo agente,
 * descrevendo seus parâmetros e esquema de uso.
 *
 * @category Models
 */
class AiAutopilotTool extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_autopilot_tools';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'handler_class',
        'display_name',
        'description',
        'parameters_schema',
        'is_system',
        'is_active',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'parameters_schema' => 'array',
        'is_system' => 'boolean',
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
