<?php

declare(strict_types=1);

namespace Domain\Configuration\Models;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de Notificação enviada.
 *
 * Mantém o histórico de notificações disparadas pelo sistema para auditoria,
 * tracking de entrega e controle de status (lida, pendente, falha).
 *
 * @category Models
 */
final class ConfigurationNotification extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'configuration_notifications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'channel',
        'status',
        'sent_at',
        'read_at',
        'error_message',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_READ = 'read';

    /**
     * Relacionamento com o Tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PlatformTenant::class, 'tenant_id');
    }

    /**
     * Relacionamento com o Usuário destinatário.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class);
    }

    /**
     * Marcar notificação como enviada com sucesso.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Marcar notificação como lida pelo usuário.
     */
    public function markAsRead(): void
    {
        if ($this->status !== self::STATUS_READ) {
            $this->update([
                'status' => self::STATUS_READ,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Registrar falha no envio da notificação.
     *
     * @param  string  $error  Mensagem descritiva do erro ocorrido.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
        ]);
    }

    /**
     * Escopo para buscar notificações não lidas.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_READ);
    }
}
