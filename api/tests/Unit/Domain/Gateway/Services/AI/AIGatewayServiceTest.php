<?php

declare(strict_types=1);

use Domain\Gateway\Contracts\GatewayClientInterface;
use Domain\Gateway\DTOs\AI\AICompletionRequest;
use Domain\Gateway\DTOs\AI\AICompletionResponse;
use Domain\Gateway\DTOs\GatewayMessage;
use Domain\Gateway\DTOs\GatewayResponse;
use Domain\Gateway\Enums\AIFinishReason;
use Domain\Gateway\Enums\GatewayDomain;
use Domain\Gateway\Enums\GatewayProvider;
use Domain\Gateway\Exceptions\GatewayException;
use Domain\Gateway\Services\AI\AIGatewayService;

describe('AIGatewayService', function (): void {
    it('creates correct GatewayMessage and calls client on complete()', function (): void {
        $client = Mockery::mock(GatewayClientInterface::class);

        $responseData = [
            'content' => 'Hello! How can I help?',
            'promptTokens' => 10,
            'completionTokens' => 8,
            'totalTokens' => 18,
            'model' => 'gpt-4o',
            'finishReason' => 'stop',
        ];

        $gatewayResponse = new GatewayResponse(
            correlationId: 'test-correlation',
            timestamp: '2026-01-28T12:00:00+00:00',
            success: true,
            data: $responseData,
        );

        $client->shouldReceive('send')
            ->once()
            ->withArgs(fn (GatewayMessage $message, int $timeout): bool => $message->domain === GatewayDomain::AI
                && $message->action === 'complete'
                && $message->provider === 'openai'
                && isset($message->payload['messages'])
                && $timeout === 180)
            ->andReturn($gatewayResponse);

        $service = new AIGatewayService($client);

        $request = AICompletionRequest::create([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $response = $service->complete($request);

        expect($response)
            ->toBeInstanceOf(AICompletionResponse::class)
            ->and($response->content)->toBe('Hello! How can I help?')
            ->and($response->finishReason)->toBe(AIFinishReason::STOP);
    });

    it('throws GatewayException when response data is null', function (): void {
        $client = Mockery::mock(GatewayClientInterface::class);

        $gatewayResponse = new GatewayResponse(
            correlationId: 'test-correlation',
            timestamp: '2026-01-28T12:00:00+00:00',
            success: true,
        );

        $client->shouldReceive('send')
            ->once()
            ->andReturn($gatewayResponse);

        $service = new AIGatewayService($client);

        $request = AICompletionRequest::create([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        expect(fn (): \Domain\Gateway\DTOs\AI\AICompletionResponse => $service->complete($request))
            ->toThrow(GatewayException::class, 'Gateway returned success without data');
    });

    it('returns configured provider via getProvider()', function (): void {
        $client = Mockery::mock(GatewayClientInterface::class);

        $serviceOpenAI = new AIGatewayService($client);
        expect($serviceOpenAI->getProvider())->toBe('openai');

        $serviceGemini = new AIGatewayService(
            client: $client,
            defaultProvider: GatewayProvider::GEMINI,
        );
        expect($serviceGemini->getProvider())->toBe('gemini');
    });

    it('uses configured timeout when calling client', function (): void {
        $client = Mockery::mock(GatewayClientInterface::class);

        $responseData = [
            'content' => 'Response',
            'promptTokens' => 5,
            'completionTokens' => 3,
            'totalTokens' => 8,
            'model' => 'gpt-4o',
            'finishReason' => 'stop',
        ];

        $gatewayResponse = new GatewayResponse(
            correlationId: 'test-correlation',
            timestamp: '2026-01-28T12:00:00+00:00',
            success: true,
            data: $responseData,
        );

        $client->shouldReceive('send')
            ->once()
            ->withArgs(function (GatewayMessage $message, int $timeout): bool {
                return $timeout === 300; // Custom timeout
            })
            ->andReturn($gatewayResponse);

        $service = new AIGatewayService(
            client: $client,
            timeoutSeconds: 300,
        );

        $request = AICompletionRequest::create([['role' => 'user', 'content' => 'Test']]);

        $service->complete($request);
    });

    it('uses correct provider in GatewayMessage', function (): void {
        $client = Mockery::mock(GatewayClientInterface::class);

        $responseData = [
            'content' => 'Gemini response',
            'promptTokens' => 10,
            'completionTokens' => 5,
            'totalTokens' => 15,
            'model' => 'gemini-1.5-pro',
            'finishReason' => 'stop',
        ];

        $gatewayResponse = new GatewayResponse(
            correlationId: 'test-correlation',
            timestamp: '2026-01-28T12:00:00+00:00',
            success: true,
            data: $responseData,
        );

        $client->shouldReceive('send')
            ->once()
            ->withArgs(fn (GatewayMessage $message): bool => $message->provider === 'gemini')
            ->andReturn($gatewayResponse);

        $service = new AIGatewayService(
            client: $client,
            defaultProvider: GatewayProvider::GEMINI,
        );

        $request = AICompletionRequest::create([['role' => 'user', 'content' => 'Test']]);

        $service->complete($request);
    });

    it('passes request payload correctly to GatewayMessage', function (): void {
        $client = Mockery::mock(GatewayClientInterface::class);

        $responseData = [
            'content' => 'Response',
            'promptTokens' => 5,
            'completionTokens' => 3,
            'totalTokens' => 8,
            'model' => 'gpt-4o',
            'finishReason' => 'stop',
        ];

        $gatewayResponse = new GatewayResponse(
            correlationId: 'test-correlation',
            timestamp: '2026-01-28T12:00:00+00:00',
            success: true,
            data: $responseData,
        );

        $client->shouldReceive('send')
            ->once()
            ->withArgs(fn (GatewayMessage $message): bool => $message->payload['messages'] === [['role' => 'user', 'content' => 'Complex test']]
                && $message->payload['model'] === 'gpt-4o'
                && $message->payload['maxTokens'] === 2000
                && $message->payload['temperature'] === 0.7)
            ->andReturn($gatewayResponse);

        $service = new AIGatewayService($client);

        $request = AICompletionRequest::create([['role' => 'user', 'content' => 'Complex test']])
            ->withModel('gpt-4o')
            ->withMaxTokens(2000)
            ->withTemperature(0.7);

        $service->complete($request);
    });
});
