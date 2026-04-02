<?php

declare(strict_types=1);

namespace Domain\Ai\Policies;

use Domain\Ai\Models\AiPromptMaster;
use Domain\Auth\Models\AuthUser;

/**
 * Policy para Master Prompts.
 */
final class AiPromptMasterPolicy
{
    /**
     * Determine if the user can view any master prompts.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Determine if the user can view a master prompt.
     */
    public function view(AuthUser $user, AiPromptMaster $master): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Determine if the user can create a master prompt.
     */
    public function create(AuthUser $user): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Determine if the user can update a master prompt.
     */
    public function update(AuthUser $user, AiPromptMaster $master): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Determine if the user can delete a master prompt.
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
