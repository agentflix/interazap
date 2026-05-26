<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Domain\Auth\Models\AuthUser;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lembrete automático de evento de agenda (UI/Email/Push/WhatsApp/Webhook).
 *
 * Configura o agendamento de notificações automáticas para eventos CRM
 * com suporte a múltiplos canais de envio.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $crm_event_id
 * @property string|null $auth_user_id
 * @property string $type
 * @property int $minutes_before
 * @property bool $notify_ui
 * @property bool $notify_email
 * @property bool $notify_push
 * @property bool $notify_whatsapp
 * @property bool $notify_webhook
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property bool $is_sent
 * @property \Illuminate\Support\Carbon|null $sent_at
 */
class CRMEventReminder extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'crm_event_reminders';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'crm_event_id',
        'auth_user_id',
        'type',
        'minutes_before',
        'notify_ui',
        'notify_email',
        'notify_push',
        'notify_whatsapp',
        'notify_webhook',
        'scheduled_at',
        'is_sent',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notify_ui' => 'boolean',
            'notify_email' => 'boolean',
            'notify_push' => 'boolean',
            'notify_whatsapp' => 'boolean',
            'notify_webhook' => 'boolean',
            'is_sent' => 'boolean',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CRMEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CRMEvent::class, 'crm_event_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Domain\Auth\Models\AuthUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'auth_user_id');
    }
}
