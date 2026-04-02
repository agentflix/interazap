<?php

declare(strict_types=1);

namespace Domain\Ai\Policies;

use Domain\Ai\Models\AiPromptTenant;
use Domain\Auth\Models\AuthUser;

/**
 * Policy para Tenant Prompts.
 */
final class AiPromptTenantPolicy
{
    /**
     * Determine if the user can view any tenant prompts.
     */
    public function viewAny(AuthUser $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view a tenant prompt.
     */
    public function view(AuthUser $user, AiPromptTenant $tenantPrompt): bool
    {
        return $user->tenant_id === $tenantPrompt->tenant_id;
    }

    /**
     * Determine if the user can update a tenant prompt.
     */
    public function update(AuthUser $user, AiPromptTenant $tenantPrompt): bool
    {
        return $user->tenant_id === $tenantPrompt->tenant_id;
    }

    /**
     * Determine if the user can create a tenant prompt.
     */
    public function create(AuthUser $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can delete a tenant prompt.
     */
    public function delete(AuthUser $user, AiPromptTenant $tenantPrompt): bool
    {
        return $user->tenant_id === $tenantPrompt->tenant_id;
    }

    /**
     * Determine if the user can approve/reject a quarantined prompt.
     */
    public function manageQuarantine(AuthUser $user, AiPromptTenant $tenantPrompt): bool
    {
        return $user->isSuperAdmin();
    }
}
