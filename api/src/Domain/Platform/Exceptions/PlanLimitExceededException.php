<?php

declare(strict_types=1);

namespace Domain\Platform\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exceção para bloqueio de limites de plano.
 */
final class PlanLimitExceededException extends AuthorizationException
{
    /**
     * Cria uma instância da exceção para o recurso especificado.
     *
     * @param  string  $resource  Nome do recurso que atingiu o limite (ex: 'usuários').
     * @return self Exceção formatada com mensagem descritiva.
     */
    public static function forResource(string $resource): self
    {
        return new self("Limite de {$resource} atingido. Faça upgrade do seu plano.");
    }

    /**
     * Renderiza a exceção como resposta JSON com código HTTP 403.
     *
     * @param  Request  $request  Requisição HTTP atual.
     * @return JsonResponse Resposta JSON padronizada com código PLAN_LIMIT_EXCEEDED.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => 'PLAN_LIMIT_EXCEEDED',
        ], 403);
    }
}
