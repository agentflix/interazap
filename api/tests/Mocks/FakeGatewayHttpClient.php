<?php

declare(strict_types=1);

namespace Tests\Mocks;

use Domain\Shared\Infrastructure\Gateway\GatewayHttpClient;

final class FakeGatewayHttpClient extends GatewayHttpClient
{
    /**
     * @var array<string, array<int, array{response:array<mixed>, once:bool}>>
     */
    private array $responses = [];

    /**
     * @var array<int, array{method:string,endpoint:string,payload:array<mixed>,headers:array<string,string>}>
     */
    private array $calls = [];

    public function __construct()
    {
        // Bypass parent constructor to avoid requiring config during tests.
    }

    /**
     * @param  array<mixed>  $response
     */
    public function fake(string $method, string $endpoint, array $response, bool $once = false): self
    {
        $key = $this->formatKey($method, $endpoint);
        $this->responses[$key][] = ['response' => $response, 'once' => $once];

        return $this;
    }

    /**
     * @return array<int, array{method:string,endpoint:string,payload:array<mixed>,headers:array<string,string>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    public function post(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->recordCall('POST', $endpoint, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    public function get(string $endpoint, array $query = [], array $headers = []): array
    {
        return $this->recordCall('GET', $endpoint, $query, $headers);
    }

    /**
     * @return array<mixed>
     */
    public function delete(string $endpoint, array $headers = []): array
    {
        return $this->recordCall('DELETE', $endpoint, [], $headers);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array<mixed>
     */
    private function recordCall(string $method, string $endpoint, array $payload, array $headers): array
    {
        $this->calls[] = [
            'method' => $method,
            'endpoint' => $endpoint,
            'payload' => $payload,
            'headers' => $headers,
        ];

        $key = $this->formatKey($method, $endpoint);
        $responses = $this->responses[$key] ?? [];

        if ($responses === []) {
            return [];
        }

        $responsePack = array_shift($responses);
        if (! $responsePack) {
            return [];
        }

        if ($responsePack['once'] === false) {
            array_unshift($responses, $responsePack);
        }

        $this->responses[$key] = $responses;

        return $responsePack['response'];
    }

    private function formatKey(string $method, string $endpoint): string
    {
        return strtoupper($method).' '.ltrim($endpoint, '/');
    }
}
