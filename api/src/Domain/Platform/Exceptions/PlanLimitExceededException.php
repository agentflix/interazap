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
    public static function forResource(string $resource): self
    {
        return new self("Limite de {$resource} atingido. Faça upgrade do seu plano.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => 'PLAN_LIMIT_EXCEEDED',
        ], 403);
    }
}
