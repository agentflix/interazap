<?php

declare(strict_types=1);

namespace Domain\Ai\Policies;

use Domain\Ai\Models\AiPromptSegment;
use Domain\Auth\Models\AuthUser;

/**
 * Policy para Segment Prompts (Nível 2 da hierarquia de prompts).
 *
 * Todas as operações exigem SuperAdmin com 'ai.prompts.manage'.
 * O segmento GENERAL é protegido e não pode ser excluído nem por SuperAdmin.
 */
final class AiPromptSegmentPolicy
{
    /**
     * Verifica se o usuário pode listar segment prompts.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Verifica se o usuário pode visualizar um segment prompt específico.
     */
    public function view(AuthUser $user, AiPromptSegment $segment): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Verifica se o usuário pode criar um segment prompt.
     */
    public function create(AuthUser $user): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Verifica se o usuário pode atualizar um segment prompt.
     */
    public function update(AuthUser $user, AiPromptSegment $segment): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Verifica se o usuário pode excluir um segment prompt (proibido para GENERAL).
     */
    public function delete(AuthUser $user, AiPromptSegment $segment): bool
    {
        // Cannot delete GENERAL segment
        if ($segment->isGeneral()) {
            return false;
        }

        return $this->canManageGlobalPrompts($user);
    }

    private function canManageGlobalPrompts(AuthUser $user): bool
    {
        return $user->isSuperAdmin() && $user->can('ai.prompts.manage');
    }
}
