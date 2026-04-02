<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AiEmbeddingServiceInterface;
use Domain\Ai\Exceptions\EmbeddingFailedException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Service for generating embeddings using OpenAI API via Gateway.
 *
 * Uses OpenAI text-embedding-3-small model with configured vector dimensions.
 */
final class AiEmbeddingService implements AiEmbeddingServiceInterface
{
    private const string MODEL = 'text-embedding-3-small';

    private const int DEFAULT_VECTOR_DIMENSIONS = 512;

    private const int MAX_RETRIES = 3;

    private const int RETRY_DELAY_MS = 1000;

    /**
     * Generate embedding for a single text.
     *
     * @return list<float> Vector with configured dimensions
     */
    public function embed(string $text): array
    {
        $results = $this->embedBatch([$text]);

        if (! isset($results[0])) {
            throw new EmbeddingFailedException('No embedding returned for input text');
        }

        return $results[0];
    }

    /**
     * Generate embeddings for multiple texts.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $gatewayUrl = rtrim((string) config('services.gateway.url', ''), '/');
        $gatewayApiKey = (string) config('services.gateway.api_key', '');
        $endpoint = $gatewayUrl !== '' ? $gatewayUrl.'/ai/openai/embeddings' : '/ai/openai/embeddings';

        $attempt = 0;
        $lastException = null;
        $lastStatusCode = null;
        $lastResponseBody = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $response = Http::timeout(60)
                    ->withHeaders($gatewayApiKey !== '' ? ['X-API-Key' => $gatewayApiKey] : [])
                    ->post($endpoint, [
                        'model' => self::MODEL,
                        'input' => $texts,
                        'dimensions' => $this->getDimensions(),
                    ]);

                if ($response->successful()) {
                    return $this->parseEmbeddingResponse($response->json(), count($texts));
                }

                $statusCode = $response->status();
                $lastStatusCode = $statusCode;
                $lastResponseBody = $response->body();

                // Rate limiting - use exponential backoff
                if ($statusCode === 429) {
                    $attempt++;
                    $delay = self::RETRY_DELAY_MS * pow(2, $attempt);
                    Sleep::for($delay)->milliseconds();

                    continue;
                }

                // Server errors - retry
                if ($statusCode >= 500) {
                    $attempt++;
                    Sleep::for(self::RETRY_DELAY_MS)->milliseconds();

                    continue;
                }

                // Client error - don't retry
                Log::error('OpenAI Embedding API error', [
                    'status' => $statusCode,
                    'body' => $response->body(),
                ]);

                break;
            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;

                Log::warning('OpenAI Embedding API exception', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    Sleep::for(self::RETRY_DELAY_MS)->milliseconds();
                }
            }
        }

        if ($lastException) {
            Log::error('OpenAI Embedding API failed after retries', [
                'error' => $lastException->getMessage(),
            ]);

            throw new EmbeddingFailedException(
                sprintf('Embedding generation failed after %d retries: %s', self::MAX_RETRIES, $lastException->getMessage()),
                previous: $lastException,
            );
        }

        $statusMessage = $lastStatusCode !== null ? "HTTP {$lastStatusCode}" : 'unknown error';

        throw new EmbeddingFailedException(sprintf(
            'Embedding generation failed after %d retries (%s). Response: %s',
            self::MAX_RETRIES,
            $statusMessage,
            $lastResponseBody ?? 'n/a',
        ));
    }

    /**
     * Parse embedding response from OpenAI.
     *
     * @param  array<string, mixed>|null  $response
     * @return list<list<float>>
     */
    private function parseEmbeddingResponse(?array $response, int $expectedCount): array
    {
        if (! $response || ! isset($response['data']) || ! is_array($response['data'])) {
            throw new EmbeddingFailedException('Malformed embedding response: missing data array');
        }

        if (count($response['data']) !== $expectedCount) {
            throw new EmbeddingFailedException(sprintf(
                'Malformed embedding response: expected %d embeddings, got %d',
                $expectedCount,
                count($response['data']),
            ));
        }

        $embeddings = [];
        foreach ($response['data'] as $item) {
            if (! isset($item['embedding']) || ! is_array($item['embedding'])) {
                throw new EmbeddingFailedException('Malformed embedding response: invalid embedding payload');
            }

            /** @var list<float> $vector */
            $vector = array_map('floatval', $item['embedding']);

            foreach ($vector as $value) {
                if (! is_finite($value)) {
                    throw new EmbeddingFailedException('Malformed embedding response: non-finite vector value');
                }
            }

            $embeddings[] = $vector;
        }

        return $embeddings;
    }

    private function getDimensions(): int
    {
        return (int) config('ai.embedding.dimensions', self::DEFAULT_VECTOR_DIMENSIONS);
    }
}
