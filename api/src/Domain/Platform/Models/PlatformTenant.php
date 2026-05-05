<?php

declare(strict_types=1);

namespace Domain\Platform\Models;

use Database\Factories\PlatformTenantFactory;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Billing\Models\BillingCollectionLog;
use Domain\Billing\Models\BillingPurgeReport;
use Domain\Chat\Models\ChatInstance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Tenant (Empresa/Organização).
 *
 * Entidade raiz da arquitetura multi-tenant, representando os clientes do SaaS.
 * Todos os dados da aplicação devem estar isolados por tenant_id.
 *
 * @property string $id
 * @property string $name
 * @property string|null $segment_id
 * @property string|null $plan_id
 * @property BillingTenantStatus $billing_status
 * @property \Carbon\Carbon|null $billing_locked_at
 * @property string|null $billing_lock_reason
 * @property \Carbon\Carbon|null $billing_grace_deadline
 * @property \Carbon\Carbon|null $billing_purge_deadline
 * @property \Carbon\Carbon|null $last_collection_sent_at
 * @property int $collection_count
 * @property bool $media_transcription_audio_enabled
 * @property bool $media_transcription_image_enabled
 * @property bool $media_transcription_video_enabled
 * @property int $media_transcription_audio_max_minutes
 * @property int $media_transcription_image_max_per_message
 * @property int $media_transcription_video_max_seconds
 * @property-read AiPromptSegment|null $segment
 * @property-read AiPromptTenant|null $aiPrompt
 * @property-read PlatformPlan|null $plan
 * @property-read \Illuminate\Database\Eloquent\Collection<BillingCollectionLog> $collectionLogs
 * @property-read BillingPurgeReport|null $purgeReport
 *
 * @category Models
 */
final class PlatformTenant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'platform_tenants';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'tenant_code',
        'primary_email',
        'document',
        'phone',
        'street',
        'number',
        'complement',
        'district',
        'city',
        'state',
        'zip_code',
        'asaas_customer_id',
        'billing_webhook_token',
        'is_active',
        'segment_id',
        'plan_id',
        'billing_status',
        'billing_locked_at',
        'billing_lock_reason',
        'billing_grace_deadline',
        'billing_purge_deadline',
        'last_collection_sent_at',
        'collection_count',
        'media_transcription_audio_enabled',
        'media_transcription_image_enabled',
        'media_transcription_video_enabled',
        'media_transcription_audio_max_minutes',
        'media_transcription_image_max_per_message',
        'media_transcription_video_max_seconds',
        'settings_localization',
        'settings_privacy',
        'settings_chat',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'billing_status' => BillingTenantStatus::class,
        'billing_locked_at' => 'datetime',
        'billing_grace_deadline' => 'date',
        'billing_purge_deadline' => 'date',
        'last_collection_sent_at' => 'datetime',
        'collection_count' => 'integer',
        'media_transcription_audio_enabled' => 'boolean',
        'media_transcription_image_enabled' => 'boolean',
        'media_transcription_video_enabled' => 'boolean',
        'media_transcription_audio_max_minutes' => 'integer',
        'media_transcription_image_max_per_message' => 'integer',
        'media_transcription_video_max_seconds' => 'integer',
        'settings_localization' => 'array',
        'settings_privacy' => 'array',
        'settings_chat' => 'array',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $tenant): void {
            if (! $tenant->id) {
                $tenant->id = (string) Str::orderedUuid();
            }

            if (! $tenant->billing_webhook_token) {
                $tenant->billing_webhook_token = (string) Str::uuid();
            }
        });
    }

    /**
     * Criar uma nova instância da Factory para testes.
     */
    protected static function newFactory(): PlatformTenantFactory
    {
        return PlatformTenantFactory::new();
    }

    /**
     * Relacionamento com o segmento de prompt.
     *
     * @return BelongsTo<AiPromptSegment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(AiPromptSegment::class, 'segment_id');
    }

    /**
     * Relacionamento com plano ativo do tenant.
     *
     * @return BelongsTo<PlatformPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformPlan::class, 'plan_id');
    }

    /**
     * Relacionamento com o prompt do tenant.
     *
     * @return HasOne<AiPromptTenant, $this>
     */
    public function aiPrompt(): HasOne
    {
        return $this->hasOne(AiPromptTenant::class, 'tenant_id');
    }

    /**
     * Relacionamento com as instâncias de chat (WhatsApp).
     *
     * @return HasMany<ChatInstance, $this>
     */
    public function chatInstances(): HasMany
    {
        return $this->hasMany(ChatInstance::class, 'tenant_id');
    }

    /**
     * Relacionamento com os logs de cobrança enviados ao tenant.
     *
     * @return HasMany<BillingCollectionLog, $this>
     */
    public function collectionLogs(): HasMany
    {
        return $this->hasMany(BillingCollectionLog::class, 'tenant_id');
    }

    /**
     * Relacionamento com o relatório mais recente de purge do tenant.
     *
     * @return HasOne<BillingPurgeReport, $this>
     */
    public function purgeReport(): HasOne
    {
        return $this->hasOne(BillingPurgeReport::class, 'tenant_id')->latestOfMany('purged_at');
    }

    /**
     * Verifica se o tenant está bloqueado por inadimplência.
     */
    public function isLocked(): bool
    {
        return $this->billing_status === BillingTenantStatus::LOCKED;
    }

    /**
     * Verifica se o tenant está em período de graça.
     */
    public function isInGrace(): bool
    {
        return $this->billing_status === BillingTenantStatus::GRACE;
    }

    /**
     * Verifica se o tenant está pendente de purge.
     */
    public function isPendingPurge(): bool
    {
        return $this->billing_status === BillingTenantStatus::PENDING_PURGE;
    }

    /**
     * Verifica se o tenant é a empresa principal protegida da plataforma.
     */
    public function isProtectedDefaultTenant(): bool
    {
        $defaultTenantId = (string) config('app.default_tenant_id', '');

        return $defaultTenantId !== '' && (string) $this->id === $defaultTenantId;
    }
}
