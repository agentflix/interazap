<?php

declare(strict_types=1);

namespace Domain\Ai\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Exception lançada quando não há conteúdo anterior para rollback.
 */
class AiPromptNoPreviousContentException extends Exception
{
    public function __construct()
    {
        parent::__construct('No previous content available for rollback.');
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'no_previous_content',
        ], 422);
    }
}
