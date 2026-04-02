<?php

declare(strict_types=1);

namespace Domain\Ai\Exceptions;

use Domain\Ai\Enums\AiAgentRole;
use Exception;

/**
 * Exceção lançada quando um papel não tem permissão para usar uma ferramenta.
 */
class ToolPermissionDeniedException extends Exception
{
    public function __construct(
        private readonly AiAgentRole $role,
        private readonly string $toolName,
    ) {
        parent::__construct(
            message: "Role '{$role->value}' is not allowed to use tool '{$toolName}'",
            code: 403
        );
    }

    /**
     * Retorna o papel que tentou usar a ferramenta.
     */
    public function getRole(): AiAgentRole
    {
        return $this->role;
    }

    /**
     * Retorna o nome da ferramenta.
     */
    public function getToolName(): string
    {
        return $this->toolName;
    }
}
