<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiSellerNotificationFactory;
use Domain\Ai\Enums\AiNotificationChannel;
use Domain\Ai\Enums\AiNotificationReason;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * AI Seller Notification - Notificação para vendedores.
 *
 * Representa uma notificação da IA para um vendedor,
 * com suporte a múltiplos canais (email, WhatsApp) e fallback automático.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $seller_id
 * @property string|null $notifiable_type
 * @property string|null $notifiable_id
 * @property string $message
 * @property AiNotificationReason $reason
 * @property AiNotificationChannel $channel
 * @property string $priority
 * @property int $attempts
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property string|null $error_message
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read PlatformTenant $tenant
 * @property-read AuthUser $seller
 * @property-read Model|null $notifiable
 */
class AiSellerNotification extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_seller_notifications';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'seller_id',
        'notifiable_type',
        'notifiable_id',
        'message',
        'reason',
        'channel',
        'priority',
        'attempts',
        'scheduled_at',
        'delivered_at',
        'failed_at',
        'error_message',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => AiNotificationReason::class,
            'channel' => AiNotificationChannel::class,
            'attempts' => 'integer',
            'scheduled_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Inicializa o modelo com geração automática de UUID.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Retorna a instância de factory do modelo.
     */
    protected static function newFactory(): AiSellerNotificationFactory
    {
        return AiSellerNotificationFactory::new();
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Vendedor que receberá a notificação.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'seller_id');
    }

    /**
     * Entidade que originou a notificação (ticket, negotiation, etc.).
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Filtra notificações pendentes (não entregues e não falhas).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('delivered_at')
            ->whereNull('failed_at');
    }

    /**
     * Ordena por prioridade (urgent > high > normal > low).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrderByPriority(Builder $query): Builder
    {
        return $query->orderByRaw("CASE priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'normal' THEN 3
            WHEN 'low' THEN 4
            ELSE 5
        END");
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Verifica se a notificação foi entregue.
     */
    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    /**
     * Verifica se a notificação falhou.
     */
    public function isFailed(): bool
    {
        return $this->failed_at !== null;
    }

    /**
     * Incrementa o contador de tentativas.
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Marca como entregue.
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'delivered_at' => now(),
        ]);
    }

    /**
     * Marca como falha com mensagem de erro.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'failed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Troca para o canal de fallback (email → WhatsApp).
     */
    public function switchToFallbackChannel(): void
    {
        $fallbackChannel = $this->channel === AiNotificationChannel::EMAIL
            ? AiNotificationChannel::WHATSAPP
            : AiNotificationChannel::EMAIL;

        $this->update([
            'channel' => $fallbackChannel,
            'attempts' => 0,
            'failed_at' => null,
            'error_message' => null,
        ]);
    }
}
