<?php

declare(strict_types=1);

namespace Domain\Ai\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Exception lançada quando um padrão de injection é detectado no prompt.
 */
class AiPromptInjectionDetectedException extends Exception
{
    public function __construct(
        public readonly string $patternName,
        public readonly string $regex,
    ) {
        parent::__construct(
            sprintf('Prompt injection detected: pattern "%s" matched.', $patternName)
        );
    }

    /**
     * Renderiza a exceção como resposta HTTP 422.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'prompt_injection_detected',
            'pattern' => $this->patternName,
        ], 422);
    }
}
