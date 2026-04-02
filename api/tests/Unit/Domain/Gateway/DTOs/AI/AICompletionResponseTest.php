<?php

declare(strict_types=1);

use Domain\Gateway\DTOs\AI\AICompletionResponse;
use Domain\Gateway\Enums\AIFinishReason;

describe('AICompletionResponse', function (): void {
    it('maps fields correctly from array via fromArray', function (): void {
        $data = [
            'content' => 'Hello! How can I help you today?',
            'promptTokens' => 10,
            'completionTokens' => 8,
            'totalTokens' => 18,
            'model' => 'gpt-4o',
            'finishReason' => 'stop',
        ];

        $response = AICompletionResponse::fromArray($data);

        expect($response->content)->toBe('Hello! How can I help you today?')
            ->and($response->promptTokens)->toBe(10)
            ->and($response->completionTokens)->toBe(8)
            ->and($response->totalTokens)->toBe(18)
            ->and($response->model)->toBe('gpt-4o');
    });

    it('maps finishReason to AIFinishReason enum correctly', function (): void {
        $dataStop = [
            'content' => 'Complete response.',
            'promptTokens' => 5,
            'completionTokens' => 3,
            'totalTokens' => 8,
            'model' => 'gpt-4o',
            'finishReason' => 'stop',
        ];

        $responseStop = AICompletionResponse::fromArray($dataStop);
        expect($responseStop->finishReason)->toBe(AIFinishReason::STOP);

        $dataLength = [
            'content' => 'Truncated response...',
            'promptTokens' => 5,
            'completionTokens' => 100,
            'totalTokens' => 105,
            'model' => 'gpt-4o',
            'finishReason' => 'length',
        ];

        $responseLength = AICompletionResponse::fromArray($dataLength);
        expect($responseLength->finishReason)->toBe(AIFinishReason::LENGTH);
    });

    it('handles content_filter finish reason', function (): void {
        $data = [
            'content' => '',
            'promptTokens' => 10,
            'completionTokens' => 0,
            'totalTokens' => 10,
            'model' => 'gpt-4o',
            'finishReason' => 'content_filter',
        ];

        $response = AICompletionResponse::fromArray($data);

        expect($response->finishReason)->toBe(AIFinishReason::CONTENT_FILTER);
    });

    it('handles tool_calls finish reason', function (): void {
        $data = [
            'content' => '{"action": "search"}',
            'promptTokens' => 15,
            'completionTokens' => 20,
            'totalTokens' => 35,
            'model' => 'gpt-4o',
            'finishReason' => 'tool_calls',
            'tool_calls' => [
                ['name' => 'search_knowledge', 'arguments' => '{"query":"pricing"}'],
            ],
        ];

        $response = AICompletionResponse::fromArray($data);

        expect($response->finishReason)->toBe(AIFinishReason::TOOL_CALLS)
            ->and($response->hasToolCalls())->toBeTrue()
            ->and($response->toolCalls)->toHaveCount(1);
    });

    it('returns null for finishReason when not present in data', function (): void {
        $data = [
            'content' => 'Streaming chunk...',
            'promptTokens' => 5,
            'completionTokens' => 3,
            'totalTokens' => 8,
            'model' => 'gpt-4o',
        ];

        $response = AICompletionResponse::fromArray($data);

        expect($response->finishReason)->toBeNull();
    });

    it('returns null for unknown finishReason values', function (): void {
        $data = [
            'content' => 'Response content',
            'promptTokens' => 5,
            'completionTokens' => 3,
            'totalTokens' => 8,
            'model' => 'gpt-4o',
            'finishReason' => 'unknown_reason',
        ];

        $response = AICompletionResponse::fromArray($data);

        expect($response->finishReason)->toBeNull();
    });

    it('can be constructed directly with all properties', function (): void {
        $response = new AICompletionResponse(
            content: 'Direct construction test',
            promptTokens: 20,
            completionTokens: 15,
            totalTokens: 35,
            model: 'gemini-1.5-pro',
            finishReason: AIFinishReason::STOP,
        );

        expect($response->content)->toBe('Direct construction test')
            ->and($response->promptTokens)->toBe(20)
            ->and($response->completionTokens)->toBe(15)
            ->and($response->totalTokens)->toBe(35)
            ->and($response->model)->toBe('gemini-1.5-pro')
            ->and($response->finishReason)->toBe(AIFinishReason::STOP);
    });
});
