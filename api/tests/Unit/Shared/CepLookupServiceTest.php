<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Domain\Shared\Services\CepLookupService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CepLookupServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lookup_throws_for_invalid_cep(): void
    {
        $service = new CepLookupService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CEP inválido para consulta.');
        $this->expectExceptionCode(422);

        $service->lookup('123');
    }

    public function test_lookup_returns_normalized_payload(): void
    {
        Http::fake([
            '*' => Http::response([
                'cep' => '01001-000',
                'logradouro' => 'Praça da Sé',
                'complemento' => 'lado ímpar',
                'bairro' => 'Sé',
                'localidade' => 'São Paulo',
                'uf' => 'sp',
                'ibge' => '3550308',
            ], 200),
        ]);

        $service = new CepLookupService;

        $data = $service->lookup('01001-000');

        $this->assertSame('01001000', $data['zip']);
        $this->assertSame('Praça da Sé', $data['street']);
        $this->assertSame('lado ímpar', $data['complement']);
        $this->assertSame('Sé', $data['district']);
        $this->assertSame('São Paulo', $data['city']);
        $this->assertSame('SP', $data['state']);
        $this->assertSame('3550308', $data['ibge']);
    }

    public function test_lookup_throws_when_service_returns_http_error(): void
    {
        Http::fake([
            '*' => Http::response([], 503),
        ]);

        $service = new CepLookupService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Não foi possível consultar o CEP neste momento.');
        $this->expectExceptionCode(503);

        $service->lookup('01001000');
    }

    public function test_lookup_throws_when_cep_not_found(): void
    {
        Http::fake([
            '*' => Http::response([
                'erro' => true,
            ], 200),
        ]);

        $service = new CepLookupService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CEP não encontrado.');
        $this->expectExceptionCode(404);

        $service->lookup('99999999');
    }

    public function test_lookup_throws_when_payload_is_invalid(): void
    {
        Http::fake([
            '*' => Http::response('invalid-json', 200),
        ]);

        $service = new CepLookupService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Resposta inválida do serviço de CEP.');
        $this->expectExceptionCode(502);

        $service->lookup('01001000');
    }
}
