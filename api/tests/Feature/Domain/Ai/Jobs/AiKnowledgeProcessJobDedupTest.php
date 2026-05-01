<?php

declare(strict_types=1);

use Domain\Ai\Contracts\AiChunkingServiceInterface;
use Domain\Ai\Contracts\AiEmbeddingServiceInterface;
use Domain\Ai\DTOs\ChunkDTO;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Jobs\AiKnowledgeProcessJob;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeChunkRef;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

describe('AiKnowledgeProcessJob deduplication', function (): void {
    beforeEach(function (): void {
        Storage::fake();
    });

    it('creates chunk refs when content hash already exists', function (): void {
        $dimensions = (int) config('ai.embedding.dimensions', 512);
        $tenant = PlatformTenant::factory()->create();

        // Existing document with a chunk
        $existingDocument = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->txt()
            ->ready()
            ->create();

        $existingChunk = AiKnowledgeChunk::factory()
            ->forDocument($existingDocument)
            ->create([
                'content' => 'Duplicate content',
                'content_hash' => hash('sha256', 'Duplicate content'),
                'chunk_index' => 0,
            ]);

        // New document with same content
        $newDocument = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->txt()
            ->pending()
            ->create([
                'file_path' => 'knowledge/'.$tenant->id.'/new.txt',
            ]);

        Storage::put($newDocument->file_path, 'Duplicate content');

        $chunkingService = \Mockery::mock(AiChunkingServiceInterface::class);
        $chunkingService->shouldReceive('chunk')
            ->once()
            ->with('Duplicate content', \Mockery::any())
            ->andReturn([
                new ChunkDTO(0, 'Duplicate content', 3),
            ]);

        $embeddingService = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingService->shouldReceive('embedBatch')
            ->once()
            ->andReturn([array_fill(0, $dimensions, 0.5)]);

        $job = new AiKnowledgeProcessJob($newDocument->id);
        $job->handle($chunkingService, $embeddingService);

        $newDocument->refresh();

        expect($newDocument->embedding_status)->toBe(AiEmbeddingStatus::READY)
            ->and($newDocument->chunk_count)->toBe(1)
            ->and(AiKnowledgeChunk::query()->where('document_id', $newDocument->id)->count())->toBe(0)
            ->and(AiKnowledgeChunkRef::query()->where('document_id', $newDocument->id)->count())->toBe(1);

        $ref = AiKnowledgeChunkRef::query()->where('document_id', $newDocument->id)->first();
        expect($ref->chunk_id)->toBe($existingChunk->id)
            ->and($ref->chunk_index)->toBe(0);
    });

    it('inserts new chunks when content hash does not exist', function (): void {
        $dimensions = (int) config('ai.embedding.dimensions', 512);
        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->txt()
            ->pending()
            ->create([
                'file_path' => 'knowledge/'.$tenant->id.'/doc.txt',
            ]);

        Storage::put($document->file_path, 'Unique content');

        $chunkingService = \Mockery::mock(AiChunkingServiceInterface::class);
        $chunkingService->shouldReceive('chunk')
            ->once()
            ->with('Unique content', \Mockery::any())
            ->andReturn([
                new ChunkDTO(0, 'Unique content', 3),
            ]);

        $embeddingService = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingService->shouldReceive('embedBatch')
            ->once()
            ->andReturn([array_fill(0, $dimensions, 0.5)]);

        $job = new AiKnowledgeProcessJob($document->id);
        $job->handle($chunkingService, $embeddingService);

        $document->refresh();

        expect($document->embedding_status)->toBe(AiEmbeddingStatus::READY)
            ->and(AiKnowledgeChunk::query()->where('document_id', $document->id)->count())->toBe(1)
            ->and(AiKnowledgeChunkRef::query()->where('document_id', $document->id)->count())->toBe(0);

        $chunk = AiKnowledgeChunk::query()->where('document_id', $document->id)->first();
        expect($chunk->content_hash)->toBe(hash('sha256', 'Unique content'));
    });

    it('cleans existing refs on reindex', function (): void {
        $dimensions = (int) config('ai.embedding.dimensions', 512);
        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->txt()
            ->ready()
            ->create([
                'file_path' => 'knowledge/'.$tenant->id.'/doc.txt',
            ]);

        // Create an existing chunk and ref for this document
        $existingChunk = AiKnowledgeChunk::factory()->forDocument($document)->create([
            'content' => 'Old content',
            'chunk_index' => 0,
        ]);

        AiKnowledgeChunkRef::create([
            'document_id' => $document->id,
            'tenant_id' => $document->tenant_id,
            'chunk_id' => $existingChunk->id,
            'chunk_index' => 0,
        ]);

        Storage::put($document->file_path, 'New content');

        $chunkingService = \Mockery::mock(AiChunkingServiceInterface::class);
        $chunkingService->shouldReceive('chunk')
            ->once()
            ->with('New content', \Mockery::any())
            ->andReturn([
                new ChunkDTO(0, 'New content', 3),
            ]);

        $embeddingService = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingService->shouldReceive('embedBatch')
            ->once()
            ->andReturn([array_fill(0, $dimensions, 0.5)]);

        // Simulate reindex by setting status to processing
        $document->update(['embedding_status' => AiEmbeddingStatus::PROCESSING]);

        $job = new AiKnowledgeProcessJob($document->id);
        $job->handle($chunkingService, $embeddingService);

        $document->refresh();

        expect($document->embedding_status)->toBe(AiEmbeddingStatus::READY)
            ->and(AiKnowledgeChunkRef::query()->where('document_id', $document->id)->count())->toBe(0)
            ->and(AiKnowledgeChunk::query()->where('document_id', $document->id)->count())->toBe(1);
    });
});
