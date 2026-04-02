<?php

declare(strict_types=1);

namespace Domain\Shared\Services;

use Domain\Shared\Services\Concerns\NormalizesLookupData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Serviço de consulta e enriquecimento de dados de CNPJ.
 *
 * Fornece uma interface para buscar detalhes cadastrais de empresas
 * a partir do CNPJ. Atualmente opera em modo Stub (simulação).
 *
 * @category Services
 */
class CnpjLookupService
{
    use NormalizesLookupData;

    /**
     * Consultar dados cadastrais de um CNPJ.
     *
     * Sanitiza o input e retorna um objeto de dados simulado com
     * informações de endereço e contato da empresa.
     *
     * @param  string  $cnpj  Número do CNPJ (com ou sem formatação).
     * @return array<string, mixed> Dados da empresa normalizados.
     */
    public function lookup(string $cnpj): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (! is_string($cnpj) || strlen($cnpj) !== 14) {
            throw new RuntimeException('CNPJ inválido para consulta.', 422);
        }

        $baseUrl = rtrim((string) config('services.lookup.receita_ws_url', 'https://www.receitaws.com.br/v1/cnpj'), '/');
        $timeout = (int) config('services.lookup.timeout', 5);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get("{$baseUrl}/{$cnpj}");
        } catch (ConnectionException $exception) {
            throw $exception;
        }

        if ($response->status() === 429) {
            throw new RuntimeException('Limite de consultas do serviço de CNPJ atingido.', 429);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Não foi possível consultar o CNPJ neste momento.', $response->status());
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Resposta inválida do serviço de CNPJ.', 502);
        }

        $status = strtolower((string) ($payload['status'] ?? ''));
        $message = strtolower((string) ($payload['message'] ?? ''));
        if ($status === 'error') {
            if (str_contains($message, 'too many requests') || str_contains($message, 'limite')) {
                throw new RuntimeException('Limite de consultas do serviço de CNPJ atingido.', 429);
            }

            throw new RuntimeException('CNPJ não encontrado ou indisponível para consulta.', 404);
        }

        return [
            'cnpj' => $cnpj,
            'legal_name' => $this->nullableString($payload['nome'] ?? null),
            'trade_name' => $this->nullableString($payload['fantasia'] ?? null),
            'status' => $this->nullableString($payload['situacao'] ?? null),
            'email' => $this->nullableString($payload['email'] ?? null),
            'phone' => $this->digitsOnly($payload['telefone'] ?? null),
            'street' => $this->nullableString($payload['logradouro'] ?? null),
            'number' => $this->nullableString($payload['numero'] ?? null),
            'complement' => $this->nullableString($payload['complemento'] ?? null),
            'district' => $this->nullableString($payload['bairro'] ?? null),
            'city' => $this->nullableString($payload['municipio'] ?? null),
            'state' => $this->normalizeState($payload['uf'] ?? null),
            'zip' => $this->digitsOnly($payload['cep'] ?? null),
        ];
    }
}
