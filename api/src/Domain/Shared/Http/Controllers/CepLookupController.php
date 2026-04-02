<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Controllers;

use Domain\Shared\Services\CepLookupService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Controller de consulta de CEP via ViaCEP.
 *
 * Recebe um CEP como parâmetro de rota e retorna os dados
 * de endereço normalizados (logradouro, bairro, cidade, UF).
 *
 * @category Controllers
 */
final class CepLookupController extends BaseController
{
    public function __construct(private readonly CepLookupService $service) {}

    /**
     * Consultar CEP via ViaCEP e retornar dados de endereco.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $cep  CEP a ser consultado (formato: XXXXXXX ou XXXXX-XXX).
     * @return JsonResponse Dados normalizados do endereco.
     */
    public function __invoke(Request $request, string $cep): JsonResponse
    {
        try {
            $data = $this->service->lookup($cep);
        } catch (ConnectionException) {
            return $this->error('Tempo de resposta excedido ao consultar o CEP. Continue com preenchimento manual.', 504);
        } catch (RuntimeException $exception) {
            $statusCode = $exception->getCode();
            if (! is_int($statusCode) || $statusCode < 400 || $statusCode > 599) {
                $statusCode = 422;
            }

            return $this->error($exception->getMessage(), $statusCode);
        }

        return $this->success(['address_data' => $data], 'CEP consultado');
    }
}
