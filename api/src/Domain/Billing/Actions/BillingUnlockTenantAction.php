<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Billing\Events\BillingTenantUnlockedEvent;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Desbloqueia tenant após regularização de pagamento.
 */
final class BillingUnlockTenantAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Desbloqueia o tenant e restaura o status ACTIVE, zerando todos os campos de inadimplência.
     *
     * @param  PlatformTenant  $tenant  Tenant a ser desbloqueado
     * @return PlatformTenant Tenant atualizado com status ACTIVE
     */
    public function handle(PlatformTenant $tenant): PlatformTenant
    {
        return DB::transaction(function () use ($tenant): PlatformTenant {
            $tenant->refresh();

            $tenant->forceFill([
                'billing_status' => BillingTenantStatus::ACTIVE,
                'billing_locked_at' => null,
                'billing_lock_reason' => null,
                'billing_grace_deadline' => null,
                'billing_purge_deadline' => null,
                'last_collection_sent_at' => null,
                'collection_count' => 0,
            ])->save();

            $this->forgetTenantStatusCache((string) $tenant->id);
            BillingTenantUnlockedEvent::dispatch((string) $tenant->id, now()->toIso8601String());
            $this->auditLogger->log(null, (string) $tenant->id, 'billing.tenant.unlocked', $tenant, [
                'billing_status' => BillingTenantStatus::ACTIVE->value,
            ]);

            return $tenant;
        });
    }

    /** Invalida o cache de status de billing do tenant. */
    private function forgetTenantStatusCache(string $tenantId): void
    {
        $prefix = (string) config('billing.delinquency.cache.billing_status_prefix', 'billing:tenant_status:');
        Cache::forget($prefix.$tenantId);
    }
}
