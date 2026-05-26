<?php

declare(strict_types=1);

namespace Domain\Ai\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exceção lançada quando o tenant excede o limite de armazenamento da Knowledge Base.
 *
 * Carrega os valores de uso atual, limite do plano e tamanho do arquivo
 * solicitado para facilitar a resposta informativa ao cliente.
 */
class StorageLimitExceededException extends Exception
{
    public function __construct(
        public readonly int $currentUsage,
        public readonly int $limit,
        public readonly int $requestedSize,
    ) {
        $message = sprintf(
            'Storage limit exceeded. Current usage: %d bytes, Limit: %d bytes, Requested: %d bytes',
            $currentUsage,
            $limit,
            $requestedSize
        );

        parent::__construct($message);
    }

    /**
     * Renderiza a exceção como resposta HTTP 422 com detalhes de uso e limite.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Storage limit exceeded.',
            'error' => 'storage_limit_exceeded',
            'details' => [
                'current_usage_bytes' => $this->currentUsage,
                'limit_bytes' => $this->limit,
                'requested_bytes' => $this->requestedSize,
                'available_bytes' => max(0, $this->limit - $this->currentUsage),
            ],
        ], 422);
    }
}
