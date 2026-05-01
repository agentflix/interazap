<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Services;

use Domain\Ai\Contracts\AiEmbeddingServiceInterface;
use Domain\Ai\DTOs\KnowledgeSearchResultDTO;
use Domain\Ai\Enums\AiRagSearchModeEnum;
use Domain\Ai\Services\AiRagService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AiRagServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.rag.ef_search' => 100,
            'ai.rag.vector_weight' => 0.6,
            'ai.rag.keyword_weight' => 0.4,
            'ai.rag.expand_neighbors' => false,
            'ai.rag.neighbor_window' => 1,
        ]);
    }

    private function mockDbTransaction(): void
    {
        DB::shouldReceive('transaction')
            ->andReturnUsing(function (callable $callback, int $attempts = 1) {
                return $callback();
            });
    }

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

        $this->mockDbTransaction();
        DB::shouldReceive('statement')
            ->once()
            ->with('SET LOCAL hnsw.ef_search = 100');
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

        $this->mockDbTransaction();
        DB::shouldReceive('statement')->once();
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

        $this->mockDbTransaction();
        DB::shouldReceive('statement')->once();
        DB::shouldReceive('select')
            ->once()
            ->andReturn([]);

        $service = new AiRagService($embeddingMock);
        $results = $service->search('query', 'tenant-id', 5, 0.95);

        $this->assertSame([], $results);
    }

    public function test_get_context_for_llm_formats_string(): void
    {
        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')->andReturn([0.1]);

        $this->mockDbTransaction();
        DB::shouldReceive('statement')->once();
        DB::shouldReceive('select')
            ->once()
            ->andReturn([
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

        $this->mockDbTransaction();
        DB::shouldReceive('statement')->once();
        DB::shouldReceive('select')->andReturn([]);

        $service = new AiRagService($embeddingMock);
        $context = $service->getContextForLLM('query', 'tenant');

        $this->assertEquals('', $context);
    }

    public function test_constructor_throws_when_weights_do_not_sum_to_one(): void
    {
        config(['ai.rag.vector_weight' => 0.7]);
        config(['ai.rag.keyword_weight' => 0.4]);

        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must equal 1.0');

        new AiRagService($embeddingMock);
    }

    public function test_search_sets_local_ef_search_from_config(): void
    {
        config(['ai.rag.ef_search' => 200]);

        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')->andReturn([0.1]);

        $this->mockDbTransaction();
        DB::shouldReceive('statement')
            ->once()
            ->with('SET LOCAL hnsw.ef_search = 200');
        DB::shouldReceive('select')->andReturn([]);

        $service = new AiRagService($embeddingMock);
        $service->search('query', 'tenant');
    }

    public function test_search_hybrid_uses_config_weights_in_sql(): void
    {
        config(['ai.rag.vector_weight' => 0.5]);
        config(['ai.rag.keyword_weight' => 0.5]);

        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')->andReturn([0.1]);

        $this->mockDbTransaction();
        DB::shouldReceive('statement')->once();
        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                return str_contains($sql, '((? * v.vector_score) + (? * COALESCE(k.keyword_score, 0))) as score')
                    && $bindings[4] === 0.5
                    && $bindings[5] === 0.5
                    && $bindings[6] === 0.5
                    && $bindings[7] === 0.5;
            })
            ->andReturn([]);

        $service = new AiRagService($embeddingMock);
        $service->search('query', 'tenant', mode: AiRagSearchModeEnum::HYBRID);
    }

    public function test_search_expands_neighbors_when_enabled(): void
    {
        config(['ai.rag.expand_neighbors' => true]);

        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')->andReturn([0.1]);

        $this->mockDbTransaction();
        DB::shouldReceive('statement')->once();
        DB::shouldReceive('select')
            ->once()
            ->withArgs(fn (string $sql): bool => str_contains($sql, 'WHERE ranked.score >= ?'))
            ->andReturn([
                (object) [
                    'chunk_id' => 'chunk-2',
                    'document_id' => 'doc-1',
                    'document_name' => 'Doc',
                    'content' => 'Middle',
                    'chunk_index' => 1,
                    'score' => 0.9,
                ],
            ]);

        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                return str_contains($sql, 'c.chunk_index BETWEEN ? AND ?')
                    && $bindings[2] === 0
                    && $bindings[3] === 2;
            })
            ->andReturn([
                (object) [
                    'chunk_id' => 'chunk-1',
                    'document_id' => 'doc-1',
                    'document_name' => 'Doc',
                    'content' => 'Before',
                    'chunk_index' => 0,
                ],
                (object) [
                    'chunk_id' => 'chunk-3',
                    'document_id' => 'doc-1',
                    'document_name' => 'Doc',
                    'content' => 'After',
                    'chunk_index' => 2,
                ],
            ]);

        $service = new AiRagService($embeddingMock);
        $results = $service->search('query', 'tenant', 1);

        $this->assertCount(3, $results);
        $this->assertSame('chunk-1', $results[0]->chunkId);
        $this->assertTrue($results[0]->isNeighbor);
        $this->assertSame('chunk-2', $results[0]->neighborOfChunkId);
        $this->assertSame('chunk-2', $results[1]->chunkId);
        $this->assertFalse($results[1]->isNeighbor);
        $this->assertSame('chunk-3', $results[2]->chunkId);
        $this->assertTrue($results[2]->isNeighbor);
        $this->assertNull($results[2]->score);
        $this->assertSame('chunk-2', $results[2]->neighborOfChunkId);
    }

    public function test_get_context_for_llm_shows_continuacao_for_neighbors(): void
    {
        config(['ai.rag.expand_neighbors' => true]);

        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')->andReturn([0.1]);

        $this->mockDbTransaction();
        DB::shouldReceive('statement')->once();
        DB::shouldReceive('select')
            ->once()
            ->withArgs(fn (string $sql): bool => str_contains($sql, 'WHERE ranked.score >= ?'))
            ->andReturn([
                (object) [
                    'chunk_id' => 'chunk-2',
                    'document_id' => 'doc-1',
                    'document_name' => 'Doc',
                    'content' => 'Middle',
                    'chunk_index' => 1,
                    'score' => 0.9,
                ],
            ]);

        DB::shouldReceive('select')
            ->once()
            ->withArgs(fn (string $sql): bool => str_contains($sql, 'c.chunk_index BETWEEN ? AND ?'))
            ->andReturn([
                (object) [
                    'chunk_id' => 'chunk-1',
                    'document_id' => 'doc-1',
                    'document_name' => 'Doc',
                    'content' => 'Before',
                    'chunk_index' => 0,
                ],
            ]);

        $service = new AiRagService($embeddingMock);
        $context = $service->getContextForLLM('query', 'tenant', 1);

        $this->assertStringContainsString('[Source: Doc | [continuação] | Chunk 1]', $context);
        $this->assertStringContainsString('[Source: Doc | Relevância: 90% | Chunk 2]', $context);
    }

    public function test_search_applies_file_type_filter(): void
    {
        /** @var AiEmbeddingServiceInterface&\Mockery\MockInterface $embeddingMock */
        $embeddingMock = Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingMock->shouldReceive('embed')->andReturn([0.1]);

        $this->mockDbTransaction();
        DB::shouldReceive('statement')->once();
        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $sql, array $bindings): bool {
                return str_contains($sql, 'AND d.file_type IN (?)')
                    && $bindings[2] === 'pdf';
            })
            ->andReturn([]);

        $filters = new \Domain\Ai\DTOs\KnowledgeSearchFiltersDTO(
            fileTypes: [\Domain\Ai\Enums\AiDocumentType::PDF],
        );

        $service = new AiRagService($embeddingMock);
        $service->search('query', 'tenant', filters: $filters);
    }
}
