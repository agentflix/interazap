<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\Gateway;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Cliente HTTP para comunicação interna com o Gateway NestJS.
 *
 * Encapsula configuração de timeout, retry com backoff e autenticação via
 * api_key lida do config services.gateway. Todas as respostas são retornadas
 * como arrays PHP desserializados do JSON.
 */
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
     * Lê as seguintes chaves de configuração:
     * - services.gateway.url           → base URL do gateway
     * - services.gateway.timeout       → timeout em segundos (padrão: 30)
     * - services.gateway.retry_attempts → tentativas de retry (padrão: 3)
     * - services.gateway.retry_delay_ms → delay entre retries em ms (padrão: 100)
     * - services.gateway.api_key       → chave de autenticação
     * - services.uazapi.base_url       → override opcional da base URL
     *
     * @throws \InvalidArgumentException Se services.gateway.url não estiver configurada.
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
     * Executa uma requisição POST no Gateway.
     *
     * @param  string  $endpoint  Endpoint relativo (ex: '/internal/broadcast').
     * @param  array<string, mixed>  $data  Payload JSON a enviar.
     * @param  array<string, string>  $headers  Headers HTTP adicionais.
     * @return array<mixed> Resposta deserializada do JSON.
     */
    public function post(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->request($endpoint, 'post', $data, $headers);
    }

    /**
     * Executa uma requisição GET no Gateway.
     *
     * @param  string  $endpoint  Endpoint relativo.
     * @param  array<string, mixed>  $query  Parâmetros de query string.
     * @param  array<string, string>  $headers  Headers HTTP adicionais.
     * @return array<mixed> Resposta deserializada do JSON.
     */
    public function get(string $endpoint, array $query = [], array $headers = []): array
    {
        $request = $this->newRequest($headers);

        return $request->get($this->normalizeEndpoint($endpoint), $query)
            ->throw()
            ->json() ?? [];
    }

    /**
     * Executa uma requisição DELETE no Gateway.
     *
     * @param  string  $endpoint  Endpoint relativo.
     * @param  array<string, string>  $headers  Headers HTTP adicionais.
     * @return array<mixed> Resposta deserializada do JSON.
     */
    public function delete(string $endpoint, array $headers = []): array
    {
        $request = $this->newRequest($headers);

        return $request->delete($this->normalizeEndpoint($endpoint))
            ->throw()
            ->json() ?? [];
    }

    /**
     * Executa uma requisição HTTP genérica no Gateway.
     *
     * @param  string  $endpoint  Endpoint relativo.
     * @param  string  $method  Método HTTP ('post', 'put', etc.).
     * @param  array<string, mixed>  $payload  Payload JSON a enviar.
     * @param  array<string, string>  $headers  Headers adicionais.
     * @return array<mixed> Resposta deserializada.
     */
    private function request(string $endpoint, string $method, array $payload, array $headers): array
    {
        $request = $this->newRequest($headers);
        $response = $request->{$method}($this->normalizeEndpoint($endpoint), $payload);

        return $response->throw()->json() ?? [];
    }

    /**
     * Cria um PendingRequest configurado com autenticação, timeout e retry.
     *
     * @param  array<string, string>  $headers  Headers adicionais a incluir.
     * @return PendingRequest Cliente HTTP configurado.
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

    /**
     * Normaliza o endpoint garantindo que começa com '/'.
     *
     * @param  string  $endpoint  Endpoint relativo.
     * @return string Endpoint com barra inicial.
     */
    private function normalizeEndpoint(string $endpoint): string
    {
        return '/'.ltrim($endpoint, '/');
    }
}
