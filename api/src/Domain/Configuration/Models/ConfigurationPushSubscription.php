<?php

declare(strict_types=1);

namespace Domain\Configuration\Models;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assinatura de push web por usuário.
 *
 * Armazena os dados de inscrição do navegador para entrega de notificações
 * push por endpoint, com controle de ativação e último seen.
 *
 * @category Models
 */
final class ConfigurationPushSubscription extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'configuration_push_subscriptions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'endpoint',
        'p256dh',
        'auth',
        'content_encoding',
        'is_active',
        'last_seen_at',
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
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Relacionamento com tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PlatformTenant::class, 'tenant_id');
    }

    /**
     * Relacionamento com usuário.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id');
    }
}
