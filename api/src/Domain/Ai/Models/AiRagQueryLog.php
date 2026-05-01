<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiRagQueryLogFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Log entry for RAG search queries.
 *
 * Used for quality monitoring and analytics. Never stores the raw query text.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $query_hash
 * @property int $query_length
 * @property string $mode
 * @property int $results_count
 * @property float|null $top_score
 * @property float|null $avg_score
 * @property int $latency_ms
 * @property bool $has_results
 * @property \Illuminate\Support\Carbon|null $created_at
 */
final class AiRagQueryLog extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_rag_query_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'query_hash',
        'query_length',
        'mode',
        'results_count',
        'top_score',
        'avg_score',
        'latency_ms',
        'has_results',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'query_length' => 'integer',
        'results_count' => 'integer',
        'top_score' => 'decimal:4',
        'avg_score' => 'decimal:4',
        'latency_ms' => 'integer',
        'has_results' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            if (! $model->id) {
                $model->id = (string) Str::orderedUuid();
            }

            if (! $model->created_at) {
                $model->created_at = now();
            }
        });
    }

    protected static function newFactory(): AiRagQueryLogFactory
    {
        return AiRagQueryLogFactory::new();
    }
}
