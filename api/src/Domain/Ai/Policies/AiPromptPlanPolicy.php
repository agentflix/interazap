<?php

declare(strict_types=1);

namespace Domain\Ai\Policies;

use Domain\Ai\Models\AiPromptPlan;
use Domain\Auth\Models\AuthUser;

/**
 * Policy para Plan Prompts.
 */
final class AiPromptPlanPolicy
{
    /**
     * Determine if the user can view any plan prompts.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Determine if the user can view a plan prompt.
     */
    public function view(AuthUser $user, AiPromptPlan $planPrompt): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Determine if the user can create a plan prompt.
     */
    public function create(AuthUser $user): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Determine if the user can update a plan prompt.
     */
    public function update(AuthUser $user, AiPromptPlan $planPrompt): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Determine if the user can delete a plan prompt.
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
