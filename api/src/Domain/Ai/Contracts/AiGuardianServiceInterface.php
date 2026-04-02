<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Ai\DTOs\GuardianValidationResult;

/**
 * Interface para o serviço de validação Guardian.
 */
interface AiGuardianServiceInterface
{
    /**
     * Valida o conteúdo do prompt para detectar injeções maliciosas.
     */
    public function validate(string $content): GuardianValidationResult;
}
