<?php

declare(strict_types=1);

namespace Domain\Ai\Policies;

use Domain\Ai\Models\AiPromptPlan;
use Domain\Auth\Models\AuthUser;

/**
 * Policy para Plan Prompts (Nível 3 da hierarquia de prompts).
 *
 * Todas as operações exigem que o usuário seja SuperAdmin com a permissão
 * 'ai.prompts.manage'. Define regras mandatórias por plano de assinatura.
 */
final class AiPromptPlanPolicy
{
    /**
     * Verifica se o usuário pode listar plan prompts.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Verifica se o usuário pode visualizar um plan prompt específico.
     */
    public function view(AuthUser $user, AiPromptPlan $planPrompt): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Verifica se o usuário pode criar um plan prompt.
     */
    public function create(AuthUser $user): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Verifica se o usuário pode atualizar um plan prompt.
     */
    public function update(AuthUser $user, AiPromptPlan $planPrompt): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Verifica se o usuário pode excluir um plan prompt.
     */
    public function delete(AuthUser $user, AiPromptPlan $planPrompt): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    private function canManageGlobalPrompts(AuthUser $user): bool
    {
        return $user->isSuperAdmin() && $user->can('ai.prompts.manage');
    }
}
