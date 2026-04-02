<?php

declare(strict_types=1);

namespace Domain\Reports\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Services\PlatformPlanEnforcementService;

/**
 * Policy para autorização de relatórios.
 * Delega verificação de plano para PlatformPlanEnforcementService::canViewReport().
 */
final class ReportsPolicy
{
    public function __construct(
        private readonly PlatformPlanEnforcementService $planEnforcement,
    ) {}

    /**
     * Verifica se o usuário pode ver relatórios CRM.
     *
     * Usa canViewReport com reports.crm.funnel como permissão representativa.
     */
    public function viewCrm(AuthUser $user): bool
    {
        return (bool) $user->tenant_id
            && $this->planEnforcement->canViewReport($user->tenant_id, 'reports.crm.funnel');
    }

    /**
     * Verifica se o usuário pode ver relatórios de Chat.
     *
     * Usa canViewReport com reports.chat.volume como permissão representativa.
     */
    public function viewChat(AuthUser $user): bool
    {
        return (bool) $user->tenant_id
            && $this->planEnforcement->canViewReport($user->tenant_id, 'reports.chat.volume');
    }

    /**
     * Verifica se o usuário pode ver relatórios de IA.
     *
     * Usa canViewReport com reports.ai.autopilot_performance como permissão representativa.
     */
    public function viewAi(AuthUser $user): bool
    {
        return (bool) $user->tenant_id
            && $this->planEnforcement->canViewReport($user->tenant_id, 'reports.ai.autopilot_performance');
    }

    /**
     * Verifica se o usuário pode ver relatórios de Billing.
     *
     * Admin override ja esta em canViewReport (reports.billing.revenue e admin-only).
     */
    public function viewBilling(AuthUser $user): bool
    {
        return (bool) $user->tenant_id
            && $this->planEnforcement->canViewReport($user->tenant_id, 'reports.billing.revenue');
    }

    /**
     * Verifica se o usuário pode ver relatórios de Admin.
     *
     * Usa isAdmin direto ja que nao ha permissao reports.admin.* nos modos de relatorio.
     */
    public function viewAdmin(AuthUser $user): bool
    {
        return (bool) $user->tenant_id
            && $this->planEnforcement->isAdmin($user);
    }

    /**
     * Verifica se o usuário pode exportar relatórios.
     *
     * Usa canViewReport com reports.export (disponivel apenas em modo FULL).
     */
    public function export(AuthUser $user): bool
    {
        return (bool) $user->tenant_id
            && $this->planEnforcement->canViewReport($user->tenant_id, 'reports.export');
    }
}
