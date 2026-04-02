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
 * Preferências de Notificação do Usuário.
 *
 * Permite que cada usuário configure quais tipos de notificação deseja receber
 * e por quais canais, além de definir horários de silêncio (não perturbe).
 *
 * @category Models
 */
final class ConfigurationNotificationPreference extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'configuration_notification_preferences';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'notification_type',
        'channels',
        'enabled',
        'quiet_start',
        'quiet_end',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'enabled' => 'boolean',
            'quiet_start' => 'datetime:H:i',
            'quiet_end' => 'datetime:H:i',
        ];
    }

    /**
     * Tipos de notificação disponíveis no sistema.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'new_ticket' => 'Novo Ticket',
        'ticket_assigned' => 'Ticket Atribuído',
        'ticket_updated' => 'Ticket Atualizado',
        'ticket_closed' => 'Ticket Fechado',
        'reminder' => 'Lembrete',
        'event' => 'Evento',
        'mention' => 'Menção',
        'system' => 'Sistema',
        'billing' => 'Faturamento',
    ];

    /**
     * Canais de envio suportados.
     *
     * @var array<string, string>
     */
    public const CHANNELS = [
        'ui' => 'Interface (Realtime)',
        'email' => 'Email',
        'push' => 'Push (Web)',
        'whatsapp' => 'WhatsApp',
        'webhook' => 'Webhook',
    ];

    /**
     * Relacionamento com o Tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PlatformTenant::class, 'tenant_id');
    }

    /**
     * Relacionamento com o Usuário proprietário.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class);
    }

    /**
     * Verificar se um canal específico está habilitado.
     *
     * @param  string  $channel  Identificador do canal (ex: 'email').
     */
    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels ?? ['ui'], true);
    }

    /**
     * Verificar se o momento atual está dentro do horário de silêncio.
     *
     * @return bool Retorna true se estiver no período de não perturbe.
     */
    public function isQuietHours(): bool
    {
        if (! $this->quiet_start || ! $this->quiet_end) {
            return false;
        }

        $now = now()->format('H:i');
        $start = $this->quiet_start->format('H:i');
        $end = $this->quiet_end->format('H:i');

        if ($start > $end) {
            return $now >= $start || $now <= $end;
        }

        return $now >= $start && $now <= $end;
    }
}
