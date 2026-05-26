<?php

declare(strict_types=1);

namespace Domain\Ai\Policies;

use Domain\Ai\Models\AiPromptMaster;
use Domain\Auth\Models\AuthUser;

/**
 * Policy para Master Prompts (Nível 1 da hierarquia de prompts).
 *
 * Todas as operações exigem que o usuário seja SuperAdmin com a permissão
 * 'ai.prompts.manage'. Acesso restrito à administração da plataforma.
 */
final class AiPromptMasterPolicy
{
    /**
     * Verifica se o usuário pode listar master prompts.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Verifica se o usuário pode visualizar um master prompt específico.
     */
    public function view(AuthUser $user, AiPromptMaster $master): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Verifica se o usuário pode criar um master prompt.
     */
    public function create(AuthUser $user): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Verifica se o usuário pode atualizar um master prompt.
     */
    public function update(AuthUser $user, AiPromptMaster $master): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Verifica se o usuário pode excluir um master prompt.
     */
    public function delete(AuthUser $user, AiPromptMaster $master): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    private function canManageGlobalPrompts(AuthUser $user): bool
    {
        return $user->isSuperAdmin() && $user->can('ai.prompts.manage');
    }
}
