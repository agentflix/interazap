<?php

declare(strict_types=1);

use Domain\Ai\Contracts\AiEmbeddingServiceInterface;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Ai\Services\AiRagService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config([
        'ai.rag.ef_search' => 100,
        'ai.rag.vector_weight' => 0.6,
        'ai.rag.keyword_weight' => 0.4,
        'ai.rag.expand_neighbors' => false,
        'ai.rag.neighbor_window' => 1,
    ]);
});

function generateVector(int $dimensions = 512, float $value = 1.0): array
{
    return array_fill(0, $dimensions, $value);
}

function vectorString(array $vector): string
{
    return '['.implode(',', $vector).']';
}

describe('AiRagService search logging', function (): void {
    it('creates a query log entry on every search', function (): void {
        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->ready()
            ->create();

        $vector = generateVector();
        $vectorStr = vectorString($vector);

        AiKnowledgeChunk::factory()
            ->forDocument($document)
            ->forTenant($tenant)
            ->create([
                'embedding' => $vectorStr,
                'content' => 'test content for logging',
                'chunk_index' => 0,
            ]);

        $embeddingMock = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')
            ->once()
            ->with('test query')
            ->andReturn($vector);

        $service = new AiRagService($embeddingMock);
        $results = $service->search('test query', $tenant->id);

        expect($results)->not->toBeEmpty();
        expect(\Domain\Ai\Models\AiRagQueryLog::query()->count())->toBe(1);

        $log = \Domain\Ai\Models\AiRagQueryLog::query()->first();
        expect($log->tenant_id)->toBe($tenant->id);
        expect($log->query_hash)->toBe(hash('sha256', 'test query'));
        expect($log->mode)->toBe('vector');
        expect($log->has_results)->toBeTrue();
        expect($log->results_count)->toBe(1);
        expect($log->top_score)->toEqual(1.0);
    });

    it('creates a query log entry even when no results match', function (): void {
        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->ready()
            ->create();

        $chunkVector = generateVector(value: 1.0);
        $queryVector = generateVector(value: -1.0);

        AiKnowledgeChunk::factory()
            ->forDocument($document)
            ->forTenant($tenant)
            ->create([
                'embedding' => vectorString($chunkVector),
                'content' => 'different content',
                'chunk_index' => 0,
            ]);

        $embeddingMock = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')
            ->once()
            ->andReturn($queryVector);

        $service = new AiRagService($embeddingMock);
        $results = $service->search('unrelated query', $tenant->id, minScore: 0.99);

        expect($results)->toBeEmpty();
        expect(\Domain\Ai\Models\AiRagQueryLog::query()->count())->toBe(1);

        $log = \Domain\Ai\Models\AiRagQueryLog::query()->first();
        expect($log->has_results)->toBeFalse();
        expect($log->results_count)->toBe(0);
    });

    it('does not create a query log entry for empty queries', function (): void {
        $tenant = PlatformTenant::factory()->create();

        $embeddingMock = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')->never();

        $service = new AiRagService($embeddingMock);
        $results = $service->search('   ', $tenant->id);

        expect($results)->toBeEmpty();
        expect(\Domain\Ai\Models\AiRagQueryLog::query()->count())->toBe(0);
    });
});
