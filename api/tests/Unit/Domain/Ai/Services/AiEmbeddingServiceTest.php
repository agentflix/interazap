<?php

declare(strict_types=1);

use Domain\Ai\Exceptions\EmbeddingFailedException;
use Domain\Ai\Services\AiEmbeddingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    config(['services.gateway.url' => 'http://gateway.local']);
    Sleep::fake();
});

describe('embed', function (): void {
    it('returns embedding vector for single text', function (): void {
        $dimensions = (int) config('ai.embedding.dimensions', 512);
        $embedding = array_fill(0, $dimensions, 0.1);

        Http::fake([
            'http://gateway.local/ai/openai/embeddings' => Http::response([
                'data' => [
                    ['embedding' => $embedding, 'index' => 0],
                ],
            ], 200),
        ]);

        $service = new AiEmbeddingService;
        $result = $service->embed('test text');

        expect($result)->toBeArray()
            ->toHaveCount($dimensions)
            ->and($result[0])->toBe(0.1);
    });

    it('throws exception on failure after retries', function (): void {
        Http::fake([
            '*' => Http::response('error', 500),
        ]);

        $service = new AiEmbeddingService;

        expect(fn (): array => $service->embed('test text'))
            ->toThrow(EmbeddingFailedException::class);
    });

    it('throws exception for empty string when gateway rejects input', function (): void {

        Http::fake([
            '*' => Http::response(['error' => 'empty input'], 400),
        ]);

        $service = new AiEmbeddingService;

        expect(fn (): array => $service->embed(''))
            ->toThrow(EmbeddingFailedException::class);
    });
});

describe('embedBatch', function (): void {
    it('returns empty array for empty input', function (): void {
        $service = new AiEmbeddingService;
        $result = $service->embedBatch([]);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('returns embeddings for multiple texts', function (): void {
        $dimensions = (int) config('ai.embedding.dimensions', 512);
        $embedding1 = array_fill(0, $dimensions, 0.1);
        $embedding2 = array_fill(0, $dimensions, 0.2);

        Http::fake([
            'http://gateway.local/ai/openai/embeddings' => Http::response([
                'data' => [
                    ['embedding' => $embedding1, 'index' => 0],
                    ['embedding' => $embedding2, 'index' => 1],
                ],
            ], 200),
        ]);

        $service = new AiEmbeddingService;
        $result = $service->embedBatch(['text1', 'text2']);

        expect($result)->toHaveCount(2)
            ->and($result[0][0])->toBe(0.1)
            ->and($result[1][0])->toBe(0.2);
    });

    it('handles gateway URL without trailing slash', function (): void {
        config(['services.gateway.url' => 'http://gateway.local']);

        $dimensions = (int) config('ai.embedding.dimensions', 512);
        $embedding = array_fill(0, $dimensions, 0.5);

        Http::fake([
            'http://gateway.local/ai/openai/embeddings' => Http::response([
                'data' => [
                    ['embedding' => $embedding, 'index' => 0],
                ],
            ], 200),
        ]);

        $service = new AiEmbeddingService;
        $result = $service->embedBatch(['test']);

        expect($result)->toHaveCount(1);
    });

    it('handles gateway URL with trailing slash', function (): void {
        config(['services.gateway.url' => 'http://gateway.local/']);

        $dimensions = (int) config('ai.embedding.dimensions', 512);
        $embedding = array_fill(0, $dimensions, 0.5);

        Http::fake([
            'http://gateway.local/ai/openai/embeddings' => Http::response([
                'data' => [
                    ['embedding' => $embedding, 'index' => 0],
                ],
            ], 200),
        ]);

        $service = new AiEmbeddingService;
        $result = $service->embedBatch(['test']);

        expect($result)->toHaveCount(1);
    });

    it('throws exception on client error', function (): void {
        Http::fake([
            '*' => Http::response(['error' => 'bad request'], 400),
        ]);

        $service = new AiEmbeddingService;

        expect(fn (): array => $service->embedBatch(['test']))
            ->toThrow(EmbeddingFailedException::class);
    });

    it('throws exception for malformed response', function (): void {
        Http::fake([
            '*' => Http::response(['invalid' => 'response'], 200),
        ]);

        $service = new AiEmbeddingService;

        expect(fn (): array => $service->embedBatch(['test']))
            ->toThrow(EmbeddingFailedException::class);
    });

    it('throws exception when gateway returns fewer embeddings than requested', function (): void {
        $dimensions = (int) config('ai.embedding.dimensions', 512);
        $embedding = array_fill(0, $dimensions, 0.1);

        Http::fake([
            '*' => Http::response([
                'data' => [
                    ['embedding' => $embedding, 'index' => 0],
                ],
            ], 200),
        ]);

        $service = new AiEmbeddingService;

        expect(fn (): array => $service->embedBatch(['text1', 'text2']))
            ->toThrow(EmbeddingFailedException::class, 'expected 2 embeddings, got 1');
    });

    it('throws exception when embedding payload is missing', function (): void {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    ['index' => 0],
                ],
            ], 200),
        ]);

        $service = new AiEmbeddingService;

        expect(fn (): array => $service->embedBatch(['test']))
            ->toThrow(EmbeddingFailedException::class, 'invalid embedding payload');
    });
});
