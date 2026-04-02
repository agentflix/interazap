<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Domain\Shared\Services\CnpjLookupService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CnpjLookupServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lookup_sanitizes_and_builds_payload(): void
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

        $service = new CnpjLookupService;

        $data = $service->lookup('12.345.678/0001-90');

        $this->assertSame('12345678000190', $data['cnpj']);
        $this->assertStringContainsString('Empresa', (string) $data['legal_name']);
        $this->assertStringContainsString('Fantasia', $data['trade_name']);
        $this->assertSame('01000000', $data['zip']);
    }

    public function test_lookup_throws_for_invalid_cnpj(): void
    {
        $service = new CnpjLookupService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CNPJ inválido para consulta.');

        $service->lookup('123');
    }

    public function test_lookup_throws_when_rate_limit_status_code_is_returned(): void
    {
        Http::fake([
            '*' => Http::response([], 429),
        ]);

        $service = new CnpjLookupService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(429);

        $service->lookup('12.345.678/0001-90');
    }

    public function test_lookup_throws_when_response_is_not_successful(): void
    {
        Http::fake([
            '*' => Http::response([], 503),
        ]);

        $service = new CnpjLookupService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(503);

        $service->lookup('12.345.678/0001-90');
    }

    public function test_lookup_throws_when_payload_is_error_with_limit_message(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'ERROR',
                'message' => 'Too many requests for this IP',
            ], 200),
        ]);

        $service = new CnpjLookupService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(429);

        $service->lookup('12.345.678/0001-90');
    }

    public function test_lookup_throws_when_payload_is_error_with_not_found_message(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'ERROR',
                'message' => 'CNPJ not found',
            ], 200),
        ]);

        $service = new CnpjLookupService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);

        $service->lookup('12.345.678/0001-90');
    }

    public function test_lookup_throws_connection_exception_without_wrapping(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('network down');
        });

        $service = new CnpjLookupService;

        $this->expectException(ConnectionException::class);
        $service->lookup('12.345.678/0001-90');
    }

    public function test_lookup_normalizes_nullable_and_state_fields(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'OK',
                'nome' => '  ',
                'fantasia' => null,
                'situacao' => 'ATIVA',
                'email' => null,
                'telefone' => '---',
                'logradouro' => ' Rua B ',
                'numero' => null,
                'complemento' => '',
                'bairro' => ' Centro ',
                'municipio' => ' Curitiba ',
                'uf' => 'parana',
                'cep' => 'abc',
            ], 200),
        ]);

        $service = new CnpjLookupService;
        $data = $service->lookup('12.345.678/0001-90');

        $this->assertNull($data['legal_name']);
        $this->assertNull($data['trade_name']);
        $this->assertNull($data['phone']);
        $this->assertSame('Rua B', $data['street']);
        $this->assertNull($data['number']);
        $this->assertNull($data['complement']);
        $this->assertSame('Centro', $data['district']);
        $this->assertSame('Curitiba', $data['city']);
        $this->assertNull($data['state']);
        $this->assertNull($data['zip']);
    }
}
