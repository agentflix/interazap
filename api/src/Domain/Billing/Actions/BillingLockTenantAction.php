<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Billing\Events\BillingTenantLockedEvent;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Bloqueia tenant por inadimplência.
 */
final class BillingLockTenantAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(PlatformTenant $tenant, string $reason): PlatformTenant
    {
        return DB::transaction(function () use ($tenant, $reason): PlatformTenant {
            $tenant->refresh();

            if ($tenant->billing_status === BillingTenantStatus::LOCKED) {
                return $tenant;
            }

            $tenant->forceFill([
                'billing_status' => BillingTenantStatus::LOCKED,
                'billing_locked_at' => now(),
                'billing_lock_reason' => $reason,
            ])->save();

            $this->forgetTenantStatusCache((string) $tenant->id);
            BillingTenantLockedEvent::dispatch((string) $tenant->id, $reason, now()->toIso8601String());
            $this->auditLogger->log(null, (string) $tenant->id, 'billing.tenant.locked', $tenant, [
                'reason' => $reason,
                'billing_status' => BillingTenantStatus::LOCKED->value,
            ]);

            return $tenant;
        });
    }

    private function forgetTenantStatusCache(string $tenantId): void
    {
        $prefix = (string) config('billing.delinquency.cache.billing_status_prefix', 'billing:tenant_status:');
        Cache::forget($prefix.$tenantId);
    }
}
