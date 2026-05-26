<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiRagQueryLogFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Registro de consulta ao sistema RAG.
 *
 * Armazena métricas de cada busca na Knowledge Base para monitoramento
 * de qualidade e análise de desempenho. O texto bruto da query nunca é
 * persistido — apenas o hash SHA256 para privacidade (LGPD).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $query_hash Hash SHA256 da query (sem o texto original).
 * @property int $query_length Comprimento em caracteres da query original.
 * @property string $mode Modo de busca utilizado (vector/hybrid).
 * @property int $results_count Número de chunks retornados.
 * @property float|null $top_score Score do resultado mais relevante.
 * @property float|null $avg_score Score médio dos resultados.
 * @property int $latency_ms Latência da busca em milissegundos.
 * @property bool $has_results Se a busca retornou pelo menos um resultado.
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
