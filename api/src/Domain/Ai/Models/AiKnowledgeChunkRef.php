<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * AI Knowledge Chunk Reference - Links a document to a shared chunk.
 *
 * Used for deduplication: multiple documents can reference the same chunk
 * when their content hash matches.
 *
 * @property string $id
 * @property string $document_id
 * @property string $chunk_id
 * @property int $chunk_index
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read AiKnowledgeDocument|null $document
 * @property-read AiKnowledgeChunk|null $chunk
 */
class AiKnowledgeChunkRef extends Model
{
    use BelongsToTenant;

    protected $table = 'ai_knowledge_chunk_refs';

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
        'chunk_id',
        'chunk_index',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'chunk_index' => 'integer',
        'created_at' => 'datetime',
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
     * Relacionamento com o documento.
     *
     * @return BelongsTo<AiKnowledgeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeDocument::class, 'document_id');
    }

    /**
     * Relacionamento com o chunk.
     *
     * @return BelongsTo<AiKnowledgeChunk, $this>
     */
    public function chunk(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeChunk::class, 'chunk_id');
    }
}
