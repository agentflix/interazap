<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Database\Factories\AiKnowledgeChunkFactory;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * AI Knowledge Chunk - Fragmento de documento para RAG.
 *
 * Representa um chunk de texto processado com seu embedding vetorial
 * para busca semântica via pgvector.
 *
 * @property string $id
 * @property string $document_id
 * @property string $tenant_id
 * @property int $chunk_index
 * @property string $content
 * @property int $token_count
 * @property array<int, float>|null $embedding
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read AiKnowledgeDocument|null $document
 * @property-read PlatformTenant|null $tenant
 */
class AiKnowledgeChunk extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_knowledge_chunks';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'document_id',
        'tenant_id',
        'chunk_index',
        'content',
        'token_count',
        'embedding',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'chunk_index' => 'integer',
        'token_count' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'embedding', // Vector data is too large for JSON responses
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->id) {
                $model->id = (string) Str::orderedUuid();
            }
            if (! $model->created_at) {
                $model->created_at = now();
            }
        });
    }

    /**
     * Relacionamento com o documento pai.
     *
     * @return BelongsTo<AiKnowledgeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeDocument::class, 'document_id');
    }

    /**
     * Scope para chunks de um documento específico.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForDocument(Builder $query, string $documentId): Builder
    {
        return $query->where('document_id', $documentId);
    }

    /**
     * Scope para chunks com embedding.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithEmbedding(Builder $query): Builder
    {
        return $query->whereNotNull('embedding');
    }

    /**
     * Verifica se o chunk tem embedding.
     */
    public function hasEmbedding(): bool
    {
        return $this->embedding !== null;
    }

    /**
     * Retorna um preview do conteúdo (primeiros N caracteres).
     */
    public function getContentPreview(int $length = 100): string
    {
        if (mb_strlen($this->content) <= $length) {
            return $this->content;
        }

        return mb_substr($this->content, 0, $length).'...';
    }

    protected static function newFactory(): AiKnowledgeChunkFactory
    {
        return AiKnowledgeChunkFactory::new();
    }
}
