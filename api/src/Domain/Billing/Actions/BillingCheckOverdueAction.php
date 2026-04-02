<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Billing\Events\BillingPurgeWarningEvent;
use Domain\Billing\Events\BillingTenantGraceEvent;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Avalia faturas vencidas e aplica transições de status de inadimplência.
 */
final class BillingCheckOverdueAction
{
    public function __construct(
        private readonly BillingLockTenantAction $lockTenantAction,
    ) {}

    /**
     * @return array{processed:int,grace:int,locked:int,pending_purge:int}
     */
    public function handle(bool $dryRun = false): array
    {
        if (! $this->isFeatureEnabled()) {
            return [
                'processed' => 0,
                'grace' => 0,
                'locked' => 0,
                'pending_purge' => 0,
            ];
        }

        $gracePeriodDays = (int) config('billing.delinquency.grace_period_days', 5);
        $purgeAfterDays = (int) config('billing.delinquency.purge_after_days', 30);
        $purgeWarningDaysBefore = (int) config('billing.delinquency.purge_warning_days_before', 5);
        $pendingPurgeAt = max(1, $purgeAfterDays - $purgeWarningDaysBefore);

        /** @var Collection<int, object{tenant_id:string, oldest_due_date:string}> $overdueByTenant */
        $overdueByTenant = BillingInvoice::query()
            ->withoutGlobalScopes()
            ->selectRaw('tenant_id, MIN(due_date) as oldest_due_date')
            ->where('status', BillingInvoiceStatus::OVERDUE->value)
            ->whereDate('due_date', '<', today())
            ->groupBy('tenant_id')
            ->get();

        $result = [
            'processed' => 0,
            'grace' => 0,
            'locked' => 0,
            'pending_purge' => 0,
        ];

        foreach ($overdueByTenant as $row) {
            $tenant = PlatformTenant::query()
                ->withoutGlobalScopes()
                ->find($row->tenant_id);

            if (! $tenant instanceof PlatformTenant) {
                continue;
            }

            $result['processed']++;

            $oldestDueDate = Carbon::parse($row->oldest_due_date)->startOfDay();
            $daysOverdue = (int) max(0, $oldestDueDate->diffInDays(now()->startOfDay()));

            if ($daysOverdue >= $gracePeriodDays && in_array($tenant->billing_status, [
                BillingTenantStatus::ACTIVE,
                BillingTenantStatus::GRACE,
            ], true)) {
                if (! $dryRun) {
                    if ($tenant->billing_grace_deadline === null || $tenant->billing_purge_deadline === null) {
                        $tenant->forceFill([
                            'billing_grace_deadline' => $oldestDueDate->copy()->addDays($gracePeriodDays)->toDateString(),
                            'billing_purge_deadline' => $oldestDueDate->copy()->addDays($purgeAfterDays)->toDateString(),
                        ])->save();
                    }

                    $this->lockTenantAction->handle($tenant, 'overdue_invoice');
                }

                $result['locked']++;
                $tenant->refresh();
            } elseif ($daysOverdue >= 1 && $tenant->billing_status === BillingTenantStatus::ACTIVE) {
                if (! $dryRun) {
                    $tenant->forceFill([
                        'billing_status' => BillingTenantStatus::GRACE,
                        'billing_grace_deadline' => $oldestDueDate->copy()->addDays($gracePeriodDays)->toDateString(),
                        'billing_purge_deadline' => $oldestDueDate->copy()->addDays($purgeAfterDays)->toDateString(),
                    ])->save();

                    $this->forgetTenantStatusCache((string) $tenant->id);

                    $invoice = BillingInvoice::query()
                        ->withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('status', BillingInvoiceStatus::OVERDUE->value)
                        ->orderBy('due_date')
                        ->first();

                    if ($invoice !== null) {
                        BillingTenantGraceEvent::dispatch(
                            (string) $tenant->id,
                            (string) $invoice->id,
                            (string) $tenant->billing_grace_deadline,
                            $daysOverdue,
                        );
                    }
                }

                $result['grace']++;
            }

            if ($daysOverdue >= $pendingPurgeAt && $tenant->billing_status === BillingTenantStatus::LOCKED) {
                if (! $dryRun) {
                    DB::transaction(function () use ($tenant): void {
                        $tenant->forceFill([
                            'billing_status' => BillingTenantStatus::PENDING_PURGE,
                        ])->save();

                        $this->forgetTenantStatusCache((string) $tenant->id);
                        BillingPurgeWarningEvent::dispatch(
                            (string) $tenant->id,
                            (string) $tenant->billing_purge_deadline,
                        );
                    });
                }

                $result['pending_purge']++;
            }
        }

        return $result;
    }

    private function forgetTenantStatusCache(string $tenantId): void
    {
        $prefix = (string) config('billing.delinquency.cache.billing_status_prefix', 'billing:tenant_status:');
        Cache::forget($prefix.$tenantId);
    }

    private function isFeatureEnabled(): bool
    {
        return (bool) config('billing.delinquency.enabled', true);
    }
}
