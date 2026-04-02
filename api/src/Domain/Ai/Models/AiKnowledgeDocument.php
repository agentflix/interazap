<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiKnowledgeDocumentFactory;
use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * AI Knowledge Document - Documento da base de conhecimento RAG.
 *
 * Representa um documento carregado pelo tenant para uso no sistema RAG.
 * Suporta versionamento e controle de status de processamento.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $original_filename
 * @property string $file_path
 * @property int $file_size_bytes
 * @property AiDocumentType $file_type
 * @property int $version
 * @property string|null $replaced_by
 * @property int $chunk_count
 * @property AiEmbeddingStatus $embedding_status
 * @property string|null $error_message
 * @property array<string, mixed>|null $metadata
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read PlatformTenant|null $tenant
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AiKnowledgeChunk> $chunks
 * @property-read self|null $replacedByDocument
 */
class AiKnowledgeDocument extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_knowledge_documents';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'original_filename',
        'file_path',
        'file_size_bytes',
        'file_type',
        'version',
        'replaced_by',
        'chunk_count',
        'embedding_status',
        'error_message',
        'metadata',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'file_size_bytes' => 'integer',
        'file_type' => AiDocumentType::class,
        'version' => 'integer',
        'chunk_count' => 'integer',
        'embedding_status' => AiEmbeddingStatus::class,
        'metadata' => 'array',
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
            // Delete associated chunks when document is deleted
            $model->chunks()->delete();
        });
    }

    /**
     * Relacionamento com os chunks do documento.
     *
     * @return HasMany<AiKnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(AiKnowledgeChunk::class, 'document_id');
    }

    /**
     * Relacionamento com o documento que substituiu este.
     *
     * @return BelongsTo<self, $this>
     */
    public function replacedByDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by');
    }

    /**
     * Scope para documentos ativos.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para documentos prontos para busca.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->where('embedding_status', AiEmbeddingStatus::READY->value);
    }

    /**
     * Scope para documentos de um tenant específico.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope para documentos pesquisáveis (ativos e prontos).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSearchable(Builder $query): Builder
    {
        return $query->active()->ready();
    }

    /**
     * Verifica se o documento está pronto para busca.
     */
    public function isReady(): bool
    {
        return $this->embedding_status === AiEmbeddingStatus::READY;
    }

    /**
     * Verifica se o documento está em processamento.
     */
    public function isProcessing(): bool
    {
        return $this->embedding_status === AiEmbeddingStatus::PROCESSING;
    }

    /**
     * Verifica se o documento falhou no processamento.
     */
    public function isFailed(): bool
    {
        return $this->embedding_status === AiEmbeddingStatus::FAILED;
    }

    /**
     * Verifica se o documento está pendente.
     */
    public function isPending(): bool
    {
        return $this->embedding_status === AiEmbeddingStatus::PENDING;
    }

    /**
     * Verifica se o documento pode ser reprocessado.
     */
    public function canReprocess(): bool
    {
        return $this->embedding_status->canReprocess();
    }

    /**
     * Retorna o tamanho do arquivo formatado.
     */
    public function getFormattedFileSize(): string
    {
        $bytes = $this->file_size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }

    protected static function newFactory(): AiKnowledgeDocumentFactory
    {
        return AiKnowledgeDocumentFactory::new();
    }
}
