<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Shared\Services\CnpjLookupService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CnpjLookupControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cnpj_lookup_endpoint_returns_sanitized_data(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'OK',
                'nome' => 'Empresa Teste LTDA',
                'fantasia' => 'Fantasia Teste',
                'situacao' => 'ATIVA',
                'email' => 'contato@empresa.test',
                'telefone' => '(11) 3333-4444',
                'logradouro' => 'Rua A',
                'numero' => '100',
                'complemento' => 'Sala 1',
                'bairro' => 'Centro',
                'municipio' => 'São Paulo',
                'uf' => 'SP',
                'cep' => '01000-000',
            ], 200),
        ]);

        $response = $this->getJson('/api/crm/cnpj/12345678000190')
            ->assertOk();

        $response->assertJsonPath('data.company_data.cnpj', '12345678000190');
        $response->assertJsonPath('data.company_data.zip', '01000000');
    }

    public function test_cnpj_lookup_returns_504_on_connection_timeout(): void
    {
        $fakeService = new class extends CnpjLookupService
        {
            /**
             * @return array<string, mixed>
             */
            public function lookup(string $cnpj): array
            {
                throw new ConnectionException('timeout');
            }
        };

        app()->instance(CnpjLookupService::class, $fakeService);

        $this->getJson('/api/crm/cnpj/12345678000190')
            ->assertStatus(504);
    }

    public function test_cnpj_lookup_returns_429_when_service_limit_is_reached(): void
    {
        $fakeService = new class extends CnpjLookupService
        {
            /**
             * @return array<string, mixed>
             */
            public function lookup(string $cnpj): array
            {
                throw new RuntimeException('limite', 429);
            }
        };

        app()->instance(CnpjLookupService::class, $fakeService);

        $this->getJson('/api/crm/cnpj/12345678000190')
            ->assertStatus(429);
    }

    public function test_cnpj_lookup_defaults_to_422_for_invalid_runtime_exception_code(): void
    {
        $fakeService = new class extends CnpjLookupService
        {
            /**
             * @return array<string, mixed>
             */
            public function lookup(string $cnpj): array
            {
                throw new RuntimeException('erro desconhecido', 200);
            }
        };

        app()->instance(CnpjLookupService::class, $fakeService);

        $this->getJson('/api/crm/cnpj/12345678000190')
            ->assertStatus(422)
            ->assertJsonPath('message', 'erro desconhecido');
    }
}
