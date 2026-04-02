<?php

declare(strict_types=1);

namespace Domain\Ai\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Exception lançada quando a operação requer status QUARANTINE mas o prompt não está.
 */
class AiPromptNotQuarantinedException extends Exception
{
    public function __construct(string $operation)
    {
        parent::__construct(
            sprintf('Cannot %s prompt: it is not in quarantine status.', $operation)
        );
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'prompt_not_quarantined',
        ], 422);
    }
}
