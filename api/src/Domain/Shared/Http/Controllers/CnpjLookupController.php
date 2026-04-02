<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Controllers;

use Domain\Shared\Services\CnpjLookupService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Consulta simplificada de CNPJ (stub).
 */
final class CnpjLookupController extends BaseController
{
    public function __construct(private readonly CnpjLookupService $service) {}

    /**
     * Consultar CNPJ e retornar dados da empresa.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $cnpj  CNPJ a ser consultado (formato: XX.XXX.XXX/XXXX-XX).
     * @return JsonResponse Dados normalizados da empresa.
     */
    public function __invoke(Request $request, string $cnpj): JsonResponse
    {
        try {
            $data = $this->service->lookup($cnpj);
        } catch (ConnectionException) {
            return $this->error('Tempo de resposta excedido ao consultar o CNPJ. Continue com preenchimento manual.', 504);
        } catch (RuntimeException $exception) {
            if ($exception->getCode() === 429) {
                return $this->error('Serviço de CNPJ temporariamente indisponível por limite de consultas. Continue com preenchimento manual.', 429);
            }

            $statusCode = $exception->getCode();
            if (! is_int($statusCode) || $statusCode < 400 || $statusCode > 599) {
                $statusCode = 422;
            }

            return $this->error($exception->getMessage(), $statusCode);
        }

        return $this->success(['company_data' => $data], 'CNPJ consultado');
    }
}
