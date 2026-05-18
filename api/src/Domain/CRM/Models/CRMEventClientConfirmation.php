<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Domain\Chat\Models\ChatTicket;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Confirmacao de agendamento pelo cliente.
 *
 * Representa o registro de confirmacao de um evento CRM
 * enviado ao cliente via WhatsApp, com controle de status
 * e lembretes automatizados.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $crm_event_id
 * @property string|null $crm_contact_id
 * @property string|null $chat_ticket_id
 * @property string $status
 * @property int $minutes_before
 * @property \Illuminate\Support\Carbon|null $reminder_sent_at
 * @property \Illuminate\Support\Carbon|null $response_at
 * @property \Illuminate\Support\Carbon|null $no_response_notified_at
 *
 * @category Models
 */
final class CRMEventClientConfirmation extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_DECLINED = 'declined';

    protected $table = 'crm_event_client_confirmations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'crm_event_id',
        'crm_contact_id',
        'chat_ticket_id',
        'status',
        'minutes_before',
        'reminder_sent_at',
        'response_at',
        'no_response_notified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minutes_before' => 'integer',
            'reminder_sent_at' => 'datetime',
            'response_at' => 'datetime',
            'no_response_notified_at' => 'datetime',
        ];
    }

    /**
     * @return array<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_DECLINED,
        ];
    }

    /**
     * Filtrar apenas confirmacoes pendentes.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Filtrar confirmacoes prontas para envio do lembrete.
     *
     * Retorna confirmacoes pendentes onde o horario de lembrete
     * ja passou (starts_at - minutes_before <= now) e ainda nao foi enviado.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReadyToSend(Builder $query): Builder
    {
        return $query->pending()
            ->whereNull('reminder_sent_at')
            ->whereRaw(
                "EXISTS (
                    SELECT 1 FROM crm_events
                    WHERE crm_events.id = crm_event_client_confirmations.crm_event_id
                    AND crm_events.starts_at <= NOW() + (crm_event_client_confirmations.minutes_before || ' minutes')::interval
                    AND crm_events.status != ?
                )",
                [CRMEvent::STATUS_CANCELLED]
            );
    }

    /**
     * Relacao com o evento CRM.
     *
     * @return BelongsTo<CRMEvent, $this>
     */
    public function crmEvent(): BelongsTo
    {
        return $this->belongsTo(CRMEvent::class, 'crm_event_id');
    }

    /**
     * Relacao com o contato CRM.
     *
     * @return BelongsTo<CRMContact, $this>
     */
    public function crmContact(): BelongsTo
    {
        return $this->belongsTo(CRMContact::class, 'crm_contact_id');
    }

    /**
     * Relacao com o ticket de chat.
     *
     * @return BelongsTo<ChatTicket, $this>
     */
    public function chatTicket(): BelongsTo
    {
        return $this->belongsTo(ChatTicket::class, 'chat_ticket_id');
    }
}
