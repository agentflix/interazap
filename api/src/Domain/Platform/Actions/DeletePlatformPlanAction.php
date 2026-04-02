<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformPlan;
use Domain\Shared\Scopes\TenantScope;

/**
 * Action para remoção (soft delete) de planos.
 */
final class DeletePlatformPlanAction
{
    public function execute(PlatformPlan $plan): void
    {
        $hasActiveInvoices = BillingInvoice::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('plan_id', $plan->id)
            ->whereIn('status', [
                BillingInvoiceStatus::PAID->value,
                BillingInvoiceStatus::PENDING->value,
                BillingInvoiceStatus::OVERDUE->value,
            ])
            ->exists();

        if ($hasActiveInvoices) {
            throw new \DomainException('Não é possível remover um plano com faturas ativas.');
        }

        $plan->delete();
    }
}
