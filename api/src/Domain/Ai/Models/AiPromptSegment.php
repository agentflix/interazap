<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiPromptSegmentFactory;
use Domain\Ai\Exceptions\AiPromptSegmentCannotDeleteGeneralException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Segment Prompt - Nível 2 da hierarquia de prompts.
 *
 * Define regras por vertical de negócio (Varejo, Clínica, Imobiliária, etc.).
 * O segmento GENERAL é obrigatório e não pode ser excluído.
 *
 * @property string $id
 * @property string|null $master_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $content
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read AiPromptMaster|null $master
 * @property-read Collection<int, AiPromptTenant> $tenantPrompts
 */
class AiPromptSegment extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CODE_GENERAL = 'GENERAL';

    protected $table = 'ai_prompt_segments';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'master_id',
        'code',
        'name',
        'description',
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

        static::deleting(function (self $model): void {
            if ($model->isGeneral()) {
                throw new AiPromptSegmentCannotDeleteGeneralException;
            }
        });
    }

    /**
     * Relacionamento com o master prompt.
     *
     * @return BelongsTo<AiPromptMaster, $this>
     */
    public function master(): BelongsTo
    {
        return $this->belongsTo(AiPromptMaster::class, 'master_id');
    }

    /**
     * Relacionamento com prompts de tenants.
     *
     * @return HasMany<AiPromptTenant, $this>
     */
    public function tenantPrompts(): HasMany
    {
        return $this->hasMany(AiPromptTenant::class, 'segment_id');
    }

    /**
     * Scope para segmentos ativos.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para buscar o segmento GENERAL.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeGeneral(Builder $query): Builder
    {
        return $query->where('code', self::CODE_GENERAL);
    }

    /**
     * Verifica se este é o segmento GENERAL.
     */
    public function isGeneral(): bool
    {
        return $this->code === self::CODE_GENERAL;
    }

    /**
     * Retorna o segmento GENERAL.
     */
    public static function getGeneral(): ?self
    {
        return self::query()->general()->first();
    }

    protected static function newFactory(): AiPromptSegmentFactory
    {
        return AiPromptSegmentFactory::new();
    }
}
