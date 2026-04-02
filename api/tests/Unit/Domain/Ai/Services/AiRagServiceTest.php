<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Services;

use Domain\Ai\Contracts\AiEmbeddingServiceInterface;
use Domain\Ai\DTOs\KnowledgeSearchResultDTO;
use Domain\Ai\Services\AiRagService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AiRagServiceTest extends TestCase
{
    public function test_search_returns_empty_array_if_query_is_empty(): void
    {
        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);
        $service = new AiRagService($embeddingMock);

        $result = $service->search('  ', 'tenant-id');

        $this->assertEmpty($result);
    }

    public function test_search_executes_query_and_returns_dtos(): void
    {
        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')
            ->once()
            ->with('query')
            ->andReturn([0.1, 0.2, 0.3]);

        DB::shouldReceive('select')
            ->once()
            ->withArgs(fn (string $sql, array $bindings): bool => str_contains($sql, 'WITH query_vec AS')
                && str_contains($sql, 'WHERE ranked.score >= ?')
                && count($bindings) === 4
                && $bindings[1] === 'tenant-id'
                && $bindings[2] === 0.30
                && $bindings[3] === 2)
            ->andReturn([
                (object) [
                    'chunk_id' => 'chunk-1',
                    'document_id' => 'doc-1',
                    'document_name' => 'Doc 1',
                    'content' => 'Content 1',
                    'chunk_index' => 1,
                    'score' => 0.95,
                ],
                (object) [
                    'chunk_id' => 'chunk-2',
                    'document_id' => 'doc-2',
                    'document_name' => 'Doc 2',
                    'content' => 'Content 2',
                    'chunk_index' => 2,
                    'score' => 0.85,
                ],
            ]);

        $service = new AiRagService($embeddingMock);
        $results = $service->search('query', 'tenant-id', 2);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(KnowledgeSearchResultDTO::class, $results[0]);
        $this->assertEquals('chunk-1', $results[0]->chunkId);
        $this->assertEquals(0.95, $results[0]->score);
    }

    public function test_search_filters_results_below_min_score(): void
    {
        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')
            ->once()
            ->with('query')
            ->andReturn([0.1, 0.2, 0.3]);

        DB::shouldReceive('select')
            ->once()
            ->withArgs(fn (string $sql, array $bindings): bool => str_contains($sql, 'WHERE ranked.score >= ?')
                && $bindings[2] === 0.75)
            ->andReturn([
                (object) [
                    'chunk_id' => 'chunk-1',
                    'document_id' => 'doc-1',
                    'document_name' => 'Doc 1',
                    'content' => 'Relevant content',
                    'chunk_index' => 0,
                    'score' => 0.91,
                ],
            ]);

        $service = new AiRagService($embeddingMock);
        $results = $service->search('query', 'tenant-id', 5, 0.75);

        $this->assertCount(1, $results);
        $this->assertEquals(0.91, $results[0]->score);
    }

    public function test_search_returns_empty_when_no_results_above_threshold(): void
    {
        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        DB::shouldReceive('select')
            ->once()
            ->andReturn([]);

        $service = new AiRagService($embeddingMock);
        $results = $service->search('query', 'tenant-id', 5, 0.95);

        $this->assertSame([], $results);
    }

    public function test_get_context_for_llm_formats_string(): void
    {
        // Partial mock of the service to mock the search method?
        // No, AiRagService is final. We must mock dependencies.
        // We repeat the setup or refactor.

        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')->andReturn([0.1]);

        DB::shouldReceive('select')->andReturn([
            (object) [
                'chunk_id' => '1', 'document_id' => '1', 'document_name' => 'Doc',
                'content' => 'The sky is blue.', 'chunk_index' => 1, 'score' => 1.0,
            ],
        ]);

        $service = new AiRagService($embeddingMock);
        $context = $service->getContextForLLM('query', 'tenant', 1);

        $this->assertStringContainsString('Relevant knowledge base context:', $context);
        $this->assertStringContainsString('[Source: Doc | Relevância: 100% | Chunk 2]', $context);
        $this->assertStringContainsString('The sky is blue.', $context);
    }

    public function test_get_context_returns_empty_string_if_no_results(): void
    {
        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')->andReturn([0.1]);

        DB::shouldReceive('select')->andReturn([]);

        $service = new AiRagService($embeddingMock);
        $context = $service->getContextForLLM('query', 'tenant');

        $this->assertEquals('', $context);
    }
}
