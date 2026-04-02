<?php

declare(strict_types=1);

namespace Domain\Ai\Policies;

use Domain\Ai\Models\AiPromptSegment;
use Domain\Auth\Models\AuthUser;

/**
 * Policy para Segment Prompts.
 */
final class AiPromptSegmentPolicy
{
    /**
     * Determine if the user can view any segment prompts.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Determine if the user can view a segment prompt.
     */
    public function view(AuthUser $user, AiPromptSegment $segment): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Determine if the user can create a segment prompt.
     */
    public function create(AuthUser $user): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Determine if the user can update a segment prompt.
     */
    public function update(AuthUser $user, AiPromptSegment $segment): bool
    {
        return $this->canManageGlobalPrompts($user);
    }

    /**
     * Determine if the user can delete a segment prompt.
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
