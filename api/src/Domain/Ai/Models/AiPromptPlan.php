<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiPromptPlanFactory;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Plan Prompt - Nível 3 da hierarquia de prompts.
 *
 * Define regras mandatórias por plano de assinatura.
 *
 * @property string $id
 * @property string $plan_id
 * @property string $content
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read PlatformPlan $plan
 */
class AiPromptPlan extends Model
{
    use HasFactory;

    protected $table = 'ai_prompt_plans';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'plan_id',
        'content',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
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
     * Relacionamento com o plano.
     *
     * @return BelongsTo<PlatformPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformPlan::class, 'plan_id');
    }

    /**
     * Scope para prompts de plano ativos.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Retorna o prompt do plano pelo plan_id.
     */
    public static function findByPlanId(string $planId): ?self
    {
        return self::query()->where('plan_id', $planId)->first();
    }

    protected static function newFactory(): AiPromptPlanFactory
    {
        return AiPromptPlanFactory::new();
    }
}
