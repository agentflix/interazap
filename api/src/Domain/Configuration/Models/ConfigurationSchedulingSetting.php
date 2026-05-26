<?php

declare(strict_types=1);

namespace Domain\Configuration\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Configurações de agendamento por tenant.
 *
 * Controla o comportamento de confirmação de eventos,
 * antecedência de lembretes e canais de notificação.
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
     * Define a conversão de tipos para os atributos do modelo.
     *
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
     * Buscar ou criar configuração de agendamento para um tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return self Configuração encontrada ou criada com valores padrão.
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
