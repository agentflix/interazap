<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiRagServiceInterface;
use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\KnowledgeSearchResultDTO;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Tools\SearchKnowledgeTool;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class SearchKnowledgeToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SearchKnowledgeTool $tool;

    private MockInterface $ragService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ragService = \Mockery::mock(AiRagServiceInterface::class);
        $this->ragService->shouldReceive('search')->zeroOrMoreTimes()->andReturn([]);
        $this->tool = new SearchKnowledgeTool($this->ragService);
    }

    public function test_it_implements_ai_tool_interface(): void
    {
        expect($this->tool)->toBeInstanceOf(AiToolInterface::class);
    }

    public function test_it_has_correct_name(): void
    {
        expect($this->tool->getName())->toBe('search_knowledge');
    }

    public function test_it_has_description(): void
    {
        expect($this->tool->getDescription())
            ->toBeString()
            ->not->toBeEmpty();
    }

    public function test_it_has_required_parameters(): void
    {
        $params = $this->tool->getParameters();

        expect($params)->toHaveKeys(['query', 'limit']);
        expect($params['query']['required'])->toBeTrue();
        expect($params['limit']['required'])->toBeFalse();
    }

    public function test_it_returns_results_when_chunks_exist(): void
    {
        $ragService = \Mockery::mock(AiRagServiceInterface::class);
        $ragService
            ->shouldReceive('search')
            ->andReturn([
                new KnowledgeSearchResultDTO(
                    chunkId: 'chunk-1',
                    documentId: 'doc-1',
                    documentName: 'Refund Policy',
                    content: 'This is about refund policies and how to process returns.',
                    chunkIndex: 0,
                    score: 0.93,
                ),
            ]);
        $tool = new SearchKnowledgeTool($ragService);

        $input = new ToolInputDTO(
            toolName: 'search_knowledge',
            parameters: [
                'query' => 'refund policy',
                'limit' => 5,
            ],
            context: ['tenant_id' => '00000000-0000-0000-0000-000000000001'],
        );

        $result = $tool->handle($input);

        expect($result)->toBeInstanceOf(ToolResultDTO::class);
        expect($result->success)->toBeTrue();
        expect($result->data)->toHaveKey('results');
        expect($result->data['results'])->toHaveCount(1);
    }

    public function test_it_returns_empty_results_when_no_match(): void
    {
        $input = new ToolInputDTO(
            toolName: 'search_knowledge',
            parameters: [
                'query' => 'something that does not exist',
            ],
            context: ['tenant_id' => '00000000-0000-0000-0000-000000000001'],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeTrue();
        expect($result->data['results'])->toBeEmpty();
    }

    public function test_it_fails_when_query_is_empty(): void
    {
        $input = new ToolInputDTO(
            toolName: 'search_knowledge',
            parameters: [
                'query' => '',
            ],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('Query cannot be empty');
    }
}
