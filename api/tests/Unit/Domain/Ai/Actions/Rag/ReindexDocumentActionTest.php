<?php

declare(strict_types=1);

use Domain\Ai\Actions\Rag\ReindexDocumentAction;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Jobs\AiKnowledgeProcessJob;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
});

describe('execute', function (): void {
    it('reindexes ready document', function (): void {
        $document = AiKnowledgeDocument::factory()->ready()->create([
            'chunk_count' => 5,
        ]);

        // Create some chunks
        AiKnowledgeChunk::factory()->count(5)->create([
            'document_id' => $document->id,
            'tenant_id' => $document->tenant_id,
        ]);

        $action = new ReindexDocumentAction;
        $result = $action->execute($document);

        expect($result->embedding_status)->toBe(AiEmbeddingStatus::PENDING)
            ->and($result->chunk_count)->toBe(0)
            ->and($result->error_message)->toBeNull();

        // Chunks should be deleted
        expect(\Domain\Ai\Models\AiKnowledgeChunk::query()->where('document_id', $document->id)->count())->toBe(0);

        // Job should be dispatched
        Queue::assertPushed(AiKnowledgeProcessJob::class);
    });

    it('reindexes failed document', function (): void {
        $document = AiKnowledgeDocument::factory()->failed()->create();

        $action = new ReindexDocumentAction;
        $result = $action->execute($document);

        expect($result->embedding_status)->toBe(AiEmbeddingStatus::PENDING)
            ->and($result->error_message)->toBeNull();

        Queue::assertPushed(AiKnowledgeProcessJob::class);
    });

    it('throws exception for inactive document', function (): void {
        $document = AiKnowledgeDocument::factory()->create([
            'is_active' => false,
            'embedding_status' => AiEmbeddingStatus::READY,
        ]);

        $action = new ReindexDocumentAction;

        expect(fn (): \Domain\Ai\Models\AiKnowledgeDocument => $action->execute($document))
            ->toThrow(InvalidArgumentException::class, 'Cannot reindex inactive document');
    });

    it('throws exception for processing document', function (): void {
        $document = AiKnowledgeDocument::factory()->create([
            'is_active' => true,
            'embedding_status' => AiEmbeddingStatus::PROCESSING,
        ]);

        $action = new ReindexDocumentAction;

        expect(fn (): \Domain\Ai\Models\AiKnowledgeDocument => $action->execute($document))
            ->toThrow(InvalidArgumentException::class, 'Document is currently being processed');
    });

    it('throws exception for pending document', function (): void {
        $document = AiKnowledgeDocument::factory()->create([
            'is_active' => true,
            'embedding_status' => AiEmbeddingStatus::PENDING,
        ]);

        $action = new ReindexDocumentAction;

        expect(fn (): \Domain\Ai\Models\AiKnowledgeDocument => $action->execute($document))
            ->toThrow(InvalidArgumentException::class, 'Document is currently being processed');
    });
});
