<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\Gateway;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class GatewayHttpClient
{
    private readonly string $baseUrl;

    private readonly int $timeout;

    private readonly int $connectTimeout;

    private readonly int $retryAttempts;

    private readonly int $retryDelay;

    private readonly ?string $apiKey;

    /**
     * Configura o client HTTP para comunicação com o Gateway.
     *
     * @note Resolved from config:
     *   - services.gateway.url        → base URL do gateway
     *   - services.gateway.timeout    → timeout em segundos (default: 30)
     *   - services.gateway.retry_attempts → tentativas de retry (default: 3)
     *   - services.gateway.retry_delay_ms → delay entre retries em ms (default: 100)
     *   - services.gateway.api_key    → chave de autenticação
     *   - services.uazapi.base_url    → override opcional da base URL
     */
    public function __construct()
    {
        $config = (array) config('services.gateway', []);
        $overrideBaseUrl = (string) config('services.uazapi.base_url', '');
        if ($overrideBaseUrl !== '') {
            $config['url'] = $overrideBaseUrl;
        }

        $this->baseUrl = rtrim((string) ($config['url'] ?? ''), '/');

        if ($this->baseUrl === '') {
            throw new InvalidArgumentException('services.gateway.url is not configured');
        }
        $this->timeout = (int) ($config['timeout'] ?? 30);
        $this->connectTimeout = (int) ($config['connect_timeout'] ?? 5);
        $this->retryAttempts = max(1, (int) ($config['retry_attempts'] ?? 3));
        $this->retryDelay = max(50, (int) ($config['retry_delay_ms'] ?? 100));
        $this->apiKey = $config['api_key'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array<mixed>
     */
    public function post(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->request($endpoint, 'post', $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     * @return array<mixed>
     */
    public function get(string $endpoint, array $query = [], array $headers = []): array
    {
        $request = $this->newRequest($headers);

        return $request->get($this->normalizeEndpoint($endpoint), $query)
            ->throw()
            ->json() ?? [];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<mixed>
     */
    public function delete(string $endpoint, array $headers = []): array
    {
        $request = $this->newRequest($headers);

        return $request->delete($this->normalizeEndpoint($endpoint))
            ->throw()
            ->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array<mixed>
     */
    private function request(string $endpoint, string $method, array $payload, array $headers): array
    {
        $request = $this->newRequest($headers);
        $response = $request->{$method}($this->normalizeEndpoint($endpoint), $payload);

        return $response->throw()->json() ?? [];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function newRequest(array $headers = []): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->retry($this->retryAttempts, $this->retryDelay)
            ->acceptJson();

        if ($this->apiKey) {
            $request->withHeaders(['x-api-key' => $this->apiKey]);
        }

        if ($headers !== []) {
            $request->withHeaders($headers);
        }

        return $request;
    }

    private function normalizeEndpoint(string $endpoint): string
    {
        return '/'.ltrim($endpoint, '/');
    }
}
