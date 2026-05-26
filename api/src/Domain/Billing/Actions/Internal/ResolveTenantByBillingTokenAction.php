<?php

declare(strict_types=1);

namespace Domain\Billing\Actions\Internal;

use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Str;

/**
 * Resolve um tenant pelo billing_webhook_token para uso interno do gateway.
 *
 * Busca global sem TenantScope (PlatformTenant não tem BelongsToTenant).
 * SoftDeletes scope é preservado — tenants excluídos não são resolvidos.
 *
 * @category Actions
 */
final readonly class ResolveTenantByBillingTokenAction
{
    /**
     * @return array{tenant_id: string, plan: string|null, quota_monthly: int, overflow_mode: string}|null
     */
    public function execute(string $token): ?array
    {
        if (! Str::isUuid($token)) {
            return null;
        }

        $tenant = PlatformTenant::query()
            ->with('plan')
            ->where('billing_webhook_token', $token)
            ->first();

        if (! $tenant instanceof PlatformTenant) {
            return null;
        }

        /** @var PlatformPlan|null $plan */
        $plan = $tenant->plan;

        return [
            'tenant_id' => (string) $tenant->id,
            'plan' => $plan?->slug,
            'quota_monthly' => $plan->message_limit_monthly ?? 0,
            'overflow_mode' => $tenant->effectiveOverageMode()->value,
        ];
    }
}
