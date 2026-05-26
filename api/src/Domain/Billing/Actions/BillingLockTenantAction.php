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

    /**
     * Bloqueia o tenant por inadimplência, registrando motivo e auditoria.
     *
     * Operação idempotente: retorna o tenant sem alteração se já estiver bloqueado.
     *
     * @param  PlatformTenant  $tenant  Tenant a ser bloqueado
     * @param  string  $reason  Motivo do bloqueio (ex: 'overdue_invoice')
     * @return PlatformTenant Tenant atualizado
     */
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

    /** Invalida o cache de status de billing do tenant. */
    private function forgetTenantStatusCache(string $tenantId): void
    {
        $prefix = (string) config('billing.delinquency.cache.billing_status_prefix', 'billing:tenant_status:');
        Cache::forget($prefix.$tenantId);
    }
}
