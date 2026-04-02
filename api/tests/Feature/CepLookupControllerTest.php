<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CepLookupControllerTest extends TestCase
{
    public function test_it_returns_address_data_on_success(): void
    {
        Http::fake([
            '*' => Http::response([
                'cep' => '01001-000',
                'logradouro' => 'Praça da Sé',
                'complemento' => '',
                'bairro' => 'Sé',
                'localidade' => 'São Paulo',
                'uf' => 'SP',
                'ibge' => '3550308',
            ], 200),
        ]);

        $this->getJson('/api/utils/cep/01001000')
            ->assertOk()
            ->assertJsonPath('data.address_data', [
                'zip' => '01001000',
                'street' => 'Praça da Sé',
                'complement' => '',
                'district' => 'Sé',
                'city' => 'São Paulo',
                'state' => 'SP',
                'ibge' => '3550308',
            ]);
    }

    public function test_it_returns_runtime_error_with_valid_status_code(): void
    {
        Http::fake([
            '*' => Http::response(['erro' => true], 200),
        ]);

        $this->getJson('/api/utils/cep/99999999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'CEP não encontrado.');
    }

    public function test_it_normalizes_runtime_error_status_when_invalid(): void
    {
        Http::fake([
            '*' => Http::response('invalid-json', 200),
        ]);

        $this->getJson('/api/utils/cep/01001000')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Resposta inválida do serviço de CEP.');
    }

    public function test_it_returns_gateway_timeout_on_connection_exception(): void
    {
        Http::fake(static function (): never {
            throw new ConnectionException('Timeout');
        });

        $this->getJson('/api/utils/cep/01001000')
            ->assertStatus(504)
            ->assertJsonPath('message', 'Tempo de resposta excedido ao consultar o CEP. Continue com preenchimento manual.');
    }
}
