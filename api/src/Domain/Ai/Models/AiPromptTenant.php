<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiPromptTenantFactory;
use Domain\Ai\Enums\AiPromptValidationStatus;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Tenant Prompt - Nível 4 da hierarquia de prompts.
 *
 * Prompt personalizado do cliente, sujeito a validação anti-injection
 * e com suporte a rollback via swap infinito.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $segment_id
 * @property string $content
 * @property string|null $previous_content
 * @property int $version
 * @property AiPromptValidationStatus $validation_status
 * @property string|null $validated_hash
 * @property \Illuminate\Support\Carbon|null $validated_at
 * @property bool $is_active
 * @property string|null $guardian_analysis
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read PlatformTenant|null $tenant
 * @property-read AiPromptSegment|null $segment
 */
class AiPromptTenant extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_prompt_tenants';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'segment_id',
        'content',
        'previous_content',
        'version',
        'validation_status',
        'validated_hash',
        'validated_at',
        'guardian_analysis',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'version' => 'integer',
        'validation_status' => AiPromptValidationStatus::class,
        'validated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->id) {
                $model->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Relacionamento com o segmento.
     *
     * @return BelongsTo<AiPromptSegment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(AiPromptSegment::class, 'segment_id');
    }

    /**
     * Scope para prompts ativos.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para prompts aprovados.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('validation_status', AiPromptValidationStatus::APPROVED->value);
    }

    /**
     * Scope para prompts em quarentena.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeQuarantined(Builder $query): Builder
    {
        return $query->where('validation_status', AiPromptValidationStatus::QUARANTINE->value);
    }

    /**
     * Verifica se o prompt está aprovado.
     */
    public function isApproved(): bool
    {
        return $this->validation_status === AiPromptValidationStatus::APPROVED;
    }

    /**
     * Verifica se o prompt está em quarentena.
     */
    public function isQuarantined(): bool
    {
        return $this->validation_status === AiPromptValidationStatus::QUARANTINE;
    }

    /**
     * Verifica se o prompt está pendente de validação.
     */
    public function isPending(): bool
    {
        return $this->validation_status === AiPromptValidationStatus::PENDING;
    }

    /**
     * Verifica se o prompt foi rejeitado.
     */
    public function isRejected(): bool
    {
        return $this->validation_status === AiPromptValidationStatus::REJECTED;
    }

    /**
     * Verifica se existe conteúdo anterior para rollback.
     */
    public function hasPreviousContent(): bool
    {
        return $this->previous_content !== null && $this->previous_content !== '';
    }

    /**
     * Retorna o prompt do tenant pelo tenant_id.
     */
    public static function findByTenantId(string $tenantId): ?self
    {
        return self::query()->where('tenant_id', $tenantId)->first();
    }

    protected static function newFactory(): AiPromptTenantFactory
    {
        return AiPromptTenantFactory::new();
    }
}
