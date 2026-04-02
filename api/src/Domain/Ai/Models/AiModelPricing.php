<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiModelPricingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * AI Model Pricing - Tabela de preços de modelos de IA.
 *
 * Armazena custos por token de entrada/saída para cada modelo,
 * permitindo cálculo preciso de custos de uso.
 *
 * @property string $id
 * @property string $provider
 * @property string $model_name
 * @property string|null $display_name
 * @property float $input_cost_per_1m
 * @property float $output_cost_per_1m
 * @property int|null $max_context_tokens
 * @property int|null $max_output_tokens
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $pricing_effective_date
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AiModelPricing extends Model
{
    use HasFactory;

    protected $table = 'ai_model_pricings';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'provider',
        'model_name',
        'display_name',
        'input_cost_per_1m',
        'output_cost_per_1m',
        'max_context_tokens',
        'max_output_tokens',
        'is_active',
        'pricing_effective_date',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_cost_per_1m' => 'float',
            'output_cost_per_1m' => 'float',
            'max_context_tokens' => 'integer',
            'max_output_tokens' => 'integer',
            'is_active' => 'boolean',
            'pricing_effective_date' => 'datetime',
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
    protected static function newFactory(): AiModelPricingFactory
    {
        return AiModelPricingFactory::new();
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Filtra modelos ativos.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Filtra por provedor.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    // =========================================================================
    // STATIC METHODS
    // =========================================================================

    /**
     * Busca pricing por nome do modelo.
     */
    public static function findByModel(string $modelName): ?self
    {
        return static::where('model_name', $modelName)
            ->active()
            ->first();
    }

    // =========================================================================
    // COST CALCULATION
    // =========================================================================

    /**
     * Calcula o custo dos tokens de entrada.
     */
    public function calculateInputCost(int $tokenCount): float
    {
        return round(($tokenCount / 1_000_000) * $this->input_cost_per_1m, 6);
    }

    /**
     * Calcula o custo dos tokens de saída.
     */
    public function calculateOutputCost(int $tokenCount): float
    {
        return round(($tokenCount / 1_000_000) * $this->output_cost_per_1m, 6);
    }

    /**
     * Calcula o custo total (entrada + saída).
     */
    public function calculateTotalCost(int $inputTokens, int $outputTokens): float
    {
        return $this->calculateInputCost($inputTokens) + $this->calculateOutputCost($outputTokens);
    }

    /**
     * Retorna o nome de exibição ou o nome do modelo.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->attributes['display_name'] ?? $this->model_name;
    }
}
