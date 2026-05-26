<?php

declare(strict_types=1);

namespace Domain\Ai\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Exception lançada quando há tentativa de deletar o segmento GENERAL.
 */
class AiPromptSegmentCannotDeleteGeneralException extends Exception
{
    public function __construct()
    {
        parent::__construct('The GENERAL segment cannot be deleted. It is a mandatory fallback segment.');
    }

    /**
     * Renderiza a exceção como resposta HTTP 403.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'segment_deletion_forbidden',
        ], 403);
    }
}
