<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiUsageLogFactory;
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
 * AI Usage Log - Registro de uso de IA.
 *
 * Armazena cada chamada à API de IA com tokens, custos e metadados.
 * LGPD: Dados devem ser purgados após 90 dias.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $user_id
 * @property string|null $ai_model_pricing_id
 * @property string $model_name
 * @property string|null $provider
 * @property int $input_tokens
 * @property int $output_tokens
 * @property float $input_cost
 * @property float $output_cost
 * @property string|null $request_id
 * @property string|null $feature
 * @property int|null $latency_ms
 * @property string|null $usable_type
 * @property string|null $usable_id
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read PlatformTenant $tenant
 * @property-read AuthUser|null $user
 * @property-read AiModelPricing|null $pricing
 * @property-read Model|null $usable
 * @property-read float $total_cost
 */
class AiUsageLog extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_usage_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'ai_model_pricing_id',
        'model_name',
        'provider',
        'input_tokens',
        'output_tokens',
        'cached_prompt_tokens',
        'input_cost',
        'output_cost',
        'request_id',
        'feature',
        'latency_ms',
        'usable_type',
        'usable_id',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cached_prompt_tokens' => 'integer',
            'input_cost' => 'float',
            'output_cost' => 'float',
            'latency_ms' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Boot method to auto-generate UUID.
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
     * Get the factory instance for this model.
     */
    protected static function newFactory(): AiUsageLogFactory
    {
        return AiUsageLogFactory::new();
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Usuário que gerou o uso (opcional).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id');
    }

    /**
     * Pricing model utilizado.
     */
    public function pricing(): BelongsTo
    {
        return $this->belongsTo(AiModelPricing::class, 'ai_model_pricing_id');
    }

    /**
     * Entidade que gerou o uso (ticket, automation, etc.).
     */
    public function usable(): MorphTo
    {
        return $this->morphTo();
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Filtra logs dos últimos N dias.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithinDays(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Filtra logs mais antigos que N dias (para LGPD purge).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOlderThanDays(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }

    /**
     * Filtra por modelo.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForModel(Builder $query, string $modelName): Builder
    {
        return $query->where('model_name', $modelName);
    }

    /**
     * Filtra por feature.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForFeature(Builder $query, string $feature): Builder
    {
        return $query->where('feature', $feature);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Custo total (input + output).
     */
    public function getTotalCostAttribute(): float
    {
        return $this->input_cost + $this->output_cost;
    }

    /**
     * Total de tokens (input + output).
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }
}
