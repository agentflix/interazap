<?php

declare(strict_types=1);

namespace Domain\Ai\Policies;

use Domain\Ai\Models\AiPromptTenant;
use Domain\Auth\Models\AuthUser;

/**
 * Policy para autorização de operações em Prompts de Tenant.
 *
 * Qualquer usuário autenticado pode visualizar e criar prompts próprios.
 * Operações de edição, exclusão e rollback são restritas ao tenant do prompt.
 * A gestão de quarentena é exclusiva do SuperAdmin.
 */
final class AiPromptTenantPolicy
{
    /**
     * Qualquer usuário autenticado pode listar prompts (filtrado por tenant no controller).
     */
    public function viewAny(AuthUser $user): bool
    {
        return true;
    }

    /**
     * Verifica se o usuário pode visualizar um prompt do próprio tenant.
     */
    public function view(AuthUser $user, AiPromptTenant $tenantPrompt): bool
    {
        return $user->tenant_id === $tenantPrompt->tenant_id;
    }

    /**
     * Verifica se o usuário pode atualizar o prompt do próprio tenant.
     */
    public function update(AuthUser $user, AiPromptTenant $tenantPrompt): bool
    {
        return $user->tenant_id === $tenantPrompt->tenant_id;
    }

    /**
     * Verifica se o usuário pode criar um prompt para o próprio tenant.
     */
    public function create(AuthUser $user): bool
    {
        return true;
    }

    /**
     * Verifica se o usuário pode excluir um prompt do próprio tenant.
     */
    public function delete(AuthUser $user, AiPromptTenant $tenantPrompt): bool
    {
        return $user->tenant_id === $tenantPrompt->tenant_id;
    }

    /**
     * Verifica se o usuário pode aprovar/rejeitar um prompt em quarentena. Exclusivo do SuperAdmin.
     */
    public function manageQuarantine(AuthUser $user, AiPromptTenant $tenantPrompt): bool
    {
        return $user->isSuperAdmin();
    }
}
