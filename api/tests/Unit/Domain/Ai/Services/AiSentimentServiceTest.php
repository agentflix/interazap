<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Services;

use Domain\Ai\Contracts\AIServiceInterface;
use Domain\Ai\Services\AiSentimentService;
use Domain\Gateway\DTOs\AI\AICompletionRequest;
use Domain\Gateway\DTOs\AI\AICompletionResponse;
use Mockery;
use Tests\TestCase;

class AiSentimentServiceTest extends TestCase
{
    public function test_analyze_keywords_returns_high_score_for_negative_message(): void
    {
        /** @var AIServiceInterface&\Mockery\MockInterface $aiService */
        $aiService = Mockery::mock(AIServiceInterface::class);
        $service = new AiSentimentService($aiService);

        $result = $service->analyzeKeywords('quero cancelar minha conta, serviço horrível');

        $this->assertGreaterThan(70, $result->score);
        $this->assertSame('keywords', $result->method);
    }

    public function test_analyze_keywords_returns_low_score_for_positive_message(): void
    {
        /** @var AIServiceInterface&\Mockery\MockInterface $aiService */
        $aiService = Mockery::mock(AIServiceInterface::class);
        $service = new AiSentimentService($aiService);

        $result = $service->analyzeKeywords('muito obrigado, excelente atendimento, perfeito!');

        $this->assertLessThan(30, $result->score);
    }

    public function test_analyze_keywords_returns_neutral_score_for_neutral_message(): void
    {
        /** @var AIServiceInterface&\Mockery\MockInterface $aiService */
        $aiService = Mockery::mock(AIServiceInterface::class);
        $service = new AiSentimentService($aiService);

        $result = $service->analyzeKeywords('qual o horário de funcionamento?');

        $this->assertSame(50, $result->score);
    }

    public function test_analyze_uses_keywords_when_confidence_is_high(): void
    {
        /** @var AIServiceInterface&\Mockery\MockInterface $aiService */
        $aiService = Mockery::mock(AIServiceInterface::class);
        $aiService->shouldNotReceive('complete');

        $service = new AiSentimentService($aiService);
        $result = $service->analyze('quero cancelar e reclamar, serviço péssimo e absurdo', 'tenant-1');

        $this->assertSame('keywords', $result->method);
        $this->assertGreaterThanOrEqual(0.7, $result->confidence);
    }

    public function test_analyze_falls_back_to_llm_when_confidence_is_low(): void
    {
        /** @var AIServiceInterface&\Mockery\MockInterface $aiService */
        $aiService = Mockery::mock(AIServiceInterface::class);
        $aiService->shouldReceive('complete')
            ->once()
            ->with(Mockery::type(AICompletionRequest::class))
            ->andReturn(new AICompletionResponse(
                content: '{"score": 78, "reasoning": "negative"}',
                promptTokens: 10,
                completionTokens: 10,
                totalTokens: 20,
                model: 'gpt-4o-mini',
                finishReason: null,
            ));

        $service = new AiSentimentService($aiService);
        $result = $service->analyze('ok', 'tenant-1');

        $this->assertSame('llm', $result->method);
        $this->assertSame(78, $result->score);
    }

    public function test_analyze_llm_clamps_score_and_falls_back_when_invalid_json(): void
    {
        /** @var AIServiceInterface&\Mockery\MockInterface $aiService */
        $aiService = Mockery::mock(AIServiceInterface::class);
        $aiService->shouldReceive('complete')
            ->once()
            ->andReturn(new AICompletionResponse(
                content: 'not-json',
                promptTokens: 10,
                completionTokens: 10,
                totalTokens: 20,
                model: 'gpt-4o-mini',
                finishReason: null,
            ));

        $service = new AiSentimentService($aiService);
        $result = $service->analyzeLLM('texto', 'tenant-1');

        $this->assertSame(50, $result->score);
        $this->assertSame('llm', $result->method);
    }

    public function test_analyze_keywords_clamps_score_between_zero_and_hundred(): void
    {
        /** @var AIServiceInterface&\Mockery\MockInterface $aiService */
        $aiService = Mockery::mock(AIServiceInterface::class);
        $service = new AiSentimentService($aiService);

        $veryNegative = $service->analyzeKeywords('procon processo advogado horrível lixo cancelar');
        $veryPositive = $service->analyzeKeywords('obrigado excelente perfeito parabéns ótimo adorei');

        $this->assertGreaterThanOrEqual(0, $veryNegative->score);
        $this->assertLessThanOrEqual(100, $veryNegative->score);
        $this->assertGreaterThanOrEqual(0, $veryPositive->score);
        $this->assertLessThanOrEqual(100, $veryPositive->score);
    }
}
