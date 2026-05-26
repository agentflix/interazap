<?php

declare(strict_types=1);

namespace Domain\Configuration\Models;

use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Webhook de Notificação.
 *
 * Configuração de endpoint externo para receber eventos de notificação
 * via POST, permitindo integrações com sistemas de terceiros.
 *
 * @category Models
 */
final class ConfigurationNotificationWebhook extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'configuration_notification_webhooks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'secret',
        'event_types',
        'is_active',
        'failure_count',
        'last_failure_at',
        'last_success_at',
    ];

    /**
     * Define a conversão de tipos para os atributos do modelo.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_types' => 'array',
            'is_active' => 'boolean',
            'last_failure_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    /**
     * Relacionamento com o Tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PlatformTenant::class, 'tenant_id');
    }
}
