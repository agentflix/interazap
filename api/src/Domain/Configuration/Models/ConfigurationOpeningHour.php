<?php

declare(strict_types=1);

namespace Domain\Configuration\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Horário de Funcionamento.
 *
 * Define os períodos de atividade operacional do Tenant por dia da semana,
 * utilizado para automação de mensagens de fora do expediente e roteamento.
 *
 * @category Models
 */
final class ConfigurationOpeningHour extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'configuration_opening_hours';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'day_of_week',
        'open_time',
        'close_time',
        'is_active',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Filtrar apenas horários ativos.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
