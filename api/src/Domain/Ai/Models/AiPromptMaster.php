<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiPromptMasterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Master Prompt - Nível 1 da hierarquia de prompts.
 *
 * Define regras globais de compliance, LGPD e segurança que são
 * invioláveis e aplicadas a todos os prompts do sistema.
 *
 * @property string $id
 * @property string $name
 * @property string $content
 * @property int $version
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collection<int, AiPromptSegment> $segments
 */
class AiPromptMaster extends Model
{
    use HasFactory;

    protected $table = 'ai_prompt_masters';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'content',
        'version',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'version' => 'integer',
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
     * Relacionamento com segmentos.
     *
     * @return HasMany<AiPromptSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(AiPromptSegment::class, 'master_id');
    }

    /**
     * Scope para masters ativos.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Incrementa a versão do master.
     */
    public function incrementVersion(): void
    {
        $this->increment('version');
    }

    protected static function newFactory(): AiPromptMasterFactory
    {
        return AiPromptMasterFactory::new();
    }
}
