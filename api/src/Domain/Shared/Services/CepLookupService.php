<?php

declare(strict_types=1);

namespace Domain\Shared\Services;

use Domain\Shared\Services\Concerns\NormalizesLookupData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Serviço de consulta de CEP via ViaCEP.
 *
 * Sanitiza o input, realiza a chamada HTTP e retorna os dados de endereço
 * normalizados (logradouro, bairro, cidade, UF, IBGE).
 */
final class CepLookupService
{
    use NormalizesLookupData;

    /**
     * Consulta os dados de endereço para um CEP informado.
     *
     * @param  string  $cep  CEP a consultar (com ou sem formatação).
     * @return array<string, string> Dados normalizados do endereço.
     *
     * @throws \RuntimeException Se o CEP for inválido, não encontrado ou o serviço indisponível.
     * @throws \Illuminate\Http\Client\ConnectionException Se o timeout for atingido.
     */
    public function lookup(string $cep): array
    {
        $cep = preg_replace('/\D+/', '', $cep);
        if (! is_string($cep) || strlen($cep) !== 8) {
            throw new RuntimeException('CEP inválido para consulta.', 422);
        }

        $baseUrl = rtrim((string) config('services.lookup.viacep_url', 'https://viacep.com.br/ws'), '/');
        $timeout = (int) config('services.lookup.timeout', 5);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get("{$baseUrl}/{$cep}/json/");
        } catch (ConnectionException $exception) {
            throw $exception;
        }

        if (! $response->successful()) {
            throw new RuntimeException('Não foi possível consultar o CEP neste momento.', $response->status());
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Resposta inválida do serviço de CEP.', 502);
        }

        if (($payload['erro'] ?? false) === true) {
            throw new RuntimeException('CEP não encontrado.', 404);
        }

        return [
            'zip' => (string) ($this->digitsOnly($payload['cep'] ?? null) ?? $cep),
            'street' => (string) ($this->nullableString($payload['logradouro'] ?? null) ?? ''),
            'complement' => (string) ($this->nullableString($payload['complemento'] ?? null) ?? ''),
            'district' => (string) ($this->nullableString($payload['bairro'] ?? null) ?? ''),
            'city' => (string) ($this->nullableString($payload['localidade'] ?? null) ?? ''),
            'state' => (string) ($this->normalizeState($payload['uf'] ?? null) ?? ''),
            'ibge' => (string) ($this->nullableString($payload['ibge'] ?? null) ?? ''),
        ];
    }
}
