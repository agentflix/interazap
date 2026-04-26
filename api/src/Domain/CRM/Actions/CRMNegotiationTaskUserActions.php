<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\Models\CRMNegotiationTask;
use Illuminate\Database\Eloquent\Collection;

/**
 * Casos de uso para tarefas do usuário autenticado.
 */
final class CRMNegotiationTaskUserActions
{
    /**
     * @return Collection<int, CRMNegotiationTask>
     */
    public function listForUser(string $tenantId, string $userId): Collection
    {
        return CRMNegotiationTask::query()
            ->where('tenant_id', $tenantId)
            ->where('auth_user_id', $userId)
            ->with([
                'negotiation:id,title,crm_company_id',
                'negotiation.company:id,name',
                'assignee:id,name',
            ])
            ->orderBy('due_date')
            ->get();
    }
}
