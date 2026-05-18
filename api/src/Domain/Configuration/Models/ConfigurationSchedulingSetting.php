<?php

declare(strict_types=1);

namespace Domain\Configuration\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuracoes de agendamento por tenant.
 *
 * Controla o comportamento de confirmacao de eventos,
 * antecedencia de lembretes e canais de notificacao.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $event_confirmation_advance_minutes
 * @property bool $event_confirmation_enabled
 * @property bool $event_confirmation_notify_ui
 * @property bool $event_confirmation_notify_push
 *
 * @category Models
 */
final class ConfigurationSchedulingSetting extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'configuration_scheduling_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'event_confirmation_advance_minutes',
        'event_confirmation_enabled',
        'event_confirmation_notify_ui',
        'event_confirmation_notify_push',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_confirmation_advance_minutes' => 'integer',
            'event_confirmation_enabled' => 'boolean',
            'event_confirmation_notify_ui' => 'boolean',
            'event_confirmation_notify_push' => 'boolean',
        ];
    }

    /**
     * Buscar ou criar configuracao de agendamento para um tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return self Configuracao encontrada ou criada com valores padrao.
     */
    public static function forTenant(string $tenantId): self
    {
        return self::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'event_confirmation_advance_minutes' => 1440,
                'event_confirmation_enabled' => true,
                'event_confirmation_notify_ui' => true,
                'event_confirmation_notify_push' => true,
            ]
        );
    }
}
