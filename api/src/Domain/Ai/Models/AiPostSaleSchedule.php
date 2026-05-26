<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiPostSaleScheduleFactory;
use Domain\Ai\Enums\AiPostSaleScheduleType;
use Domain\Ai\Enums\AiPostSaleStatus;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * AI Post-Sale Schedule - Agendamentos de pós-venda.
 *
 * Representa um agendamento de mensagem pós-venda (D+1, D+7, D+30)
 * para follow-up automático com clientes.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $negotiation_id
 * @property string|null $ticket_id
 * @property AiPostSaleScheduleType $schedule_type
 * @property \Illuminate\Support\Carbon $sale_date
 * @property \Illuminate\Support\Carbon $scheduled_at
 * @property AiPostSaleStatus $status
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property string|null $message_id
 * @property string|null $error_message
 * @property int $attempts
 * @property string|null $custom_message
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read PlatformTenant $tenant
 * @property-read CRMNegotiation $negotiation
 * @property-read ChatTicket|null $ticket
 */
class AiPostSaleSchedule extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_post_sale_schedules';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'negotiation_id',
        'ticket_id',
        'schedule_type',
        'sale_date',
        'scheduled_at',
        'status',
        'sent_at',
        'message_id',
        'error_message',
        'attempts',
        'custom_message',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schedule_type' => AiPostSaleScheduleType::class,
            'status' => AiPostSaleStatus::class,
            'sale_date' => 'datetime',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Inicializa o modelo, gera UUID automático e calcula scheduled_at quando não informado.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::orderedUuid();
            }

            // Auto-calculate scheduled_at if not set
            /** @phpstan-ignore booleanAnd.rightAlwaysTrue */
            if (empty($model->scheduled_at) && $model->getAttributes()['sale_date'] && $model->getAttributes()['schedule_type']) {
                $model->scheduled_at = $model->calculateScheduledAt();
            }
        });
    }

    /**
     * Retorna a instância de factory do modelo.
     */
    protected static function newFactory(): AiPostSaleScheduleFactory
    {
        return AiPostSaleScheduleFactory::new();
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Negociação relacionada.
     */
    public function negotiation(): BelongsTo
    {
        return $this->belongsTo(CRMNegotiation::class, 'negotiation_id');
    }

    /**
     * Ticket de chat relacionado (opcional).
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ChatTicket::class, 'ticket_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Filtra agendamentos pendentes.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AiPostSaleStatus::PENDING);
    }

    /**
     * Filtra agendamentos que já devem ser enviados.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->pending()
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Filtra por tipo de agendamento.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfType(Builder $query, AiPostSaleScheduleType $type): Builder
    {
        return $query->where('schedule_type', $type);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Calcula a data de envio baseado no tipo.
     */
    public function calculateScheduledAt(): \Illuminate\Support\Carbon
    {
        /** @var \Illuminate\Support\Carbon $saleDate */
        $saleDate = $this->sale_date;

        return $saleDate->addDays($this->schedule_type->daysOffset());
    }

    /**
     * Marca como enviado.
     */
    public function markAsSent(?string $messageId = null): void
    {
        $this->update([
            'status' => AiPostSaleStatus::SENT,
            'sent_at' => now(),
            'message_id' => $messageId,
        ]);
    }

    /**
     * Marca como falha.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => AiPostSaleStatus::FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Cancela o agendamento.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => AiPostSaleStatus::CANCELLED,
        ]);
    }

    /**
     * Incrementa tentativas.
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Verifica se pode tentar novamente.
     */
    public function canRetry(int $maxAttempts = 3): bool
    {
        return $this->attempts < $maxAttempts
            && $this->status === AiPostSaleStatus::PENDING;
    }
}
