<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AiEmbeddingServiceInterface;
use Domain\Ai\Exceptions\EmbeddingFailedException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Serviço de geração de embeddings via OpenAI (text-embedding-3-small) através do Gateway.
 *
 * Contexto: todas as requisições são roteadas para o endpoint HTTP do gateway para
 * evitar acesso direto à API da OpenAI pela api/. Implementa retry com backoff
 * exponencial para rate limiting (429) e erros de servidor (5xx).
 */
final class AiEmbeddingService implements AiEmbeddingServiceInterface
{
    private const string MODEL = 'text-embedding-3-small';

    private const int DEFAULT_VECTOR_DIMENSIONS = 512;

    private const int MAX_RETRIES = 3;

    private const int RETRY_DELAY_MS = 1000;

    /**
     * Gera embedding para um único texto.
     *
     * @param  string  $text  Texto a vetorizar.
     * @return list<float> Vetor com dimensões configuradas em ai.embedding.dimensions.
     *
     * @throws EmbeddingFailedException Se nenhum embedding for retornado.
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
     * Gera embeddings em lote para múltiplos textos.
     *
     * Realiza até MAX_RETRIES tentativas com backoff exponencial em caso de
     * rate limiting (429) ou erros de servidor (5xx). Erros de cliente (4xx)
     * não são reprocessados.
     *
     * @param  list<string>  $texts  Lista de textos a vetorizar.
     * @return list<list<float>> Lista de vetores na mesma ordem dos textos de entrada.
     *
     * @throws EmbeddingFailedException Após esgotar todas as tentativas.
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
     * Interpreta a resposta de embeddings da OpenAI.
     *
     * Valida a estrutura do payload, confere a contagem esperada de vetores
     * e garante que todos os valores são finitos (sem NaN ou Inf).
     *
     * @param  array<string, mixed>|null  $response  Payload JSON decodificado.
     * @param  int  $expectedCount  Quantidade de vetores esperados.
     * @return list<list<float>> Vetores validados.
     *
     * @throws EmbeddingFailedException Se o payload for malformado ou contiver valores inválidos.
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

    /** Retorna a dimensão do vetor configurada em ai.embedding.dimensions. */
    private function getDimensions(): int
    {
        return (int) config('ai.embedding.dimensions', self::DEFAULT_VECTOR_DIMENSIONS);
    }
}
