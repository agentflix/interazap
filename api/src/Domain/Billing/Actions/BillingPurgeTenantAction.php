<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Billing\Events\BillingTenantPurgedEvent;
use Domain\Billing\Mail\BillingCollectionMail;
use Domain\Billing\Models\BillingPayment;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Executa purge do tenant inadimplente com safeguards e dry-run.
 */
final class BillingPurgeTenantAction
{
    public function __construct(
        private readonly BillingGeneratePurgeReportAction $generateReportAction,
    ) {}

    /**
     * @return array{tenant_id:string,purged:bool,dry_run:bool,report_id:string|null}
     */
    public function handle(PlatformTenant $tenant, bool $dryRun = false): array
    {
        if (! $this->isFeatureEnabled()) {
            return [
                'tenant_id' => (string) $tenant->id,
                'purged' => false,
                'dry_run' => $dryRun,
                'report_id' => null,
            ];
        }

        $this->assertSafetyChecks($tenant);

        if ($dryRun) {
            return [
                'tenant_id' => (string) $tenant->id,
                'purged' => false,
                'dry_run' => true,
                'report_id' => null,
            ];
        }

        $report = DB::transaction(function () use ($tenant) {
            $report = $this->generateReportAction->handle($tenant);

            $this->softDeleteTenantRows((string) $tenant->id, [
                'chat_messages',
                'chat_contacts',
                'chat_instances',
                'crm_tasks',
                'crm_negotiations',
                'crm_contacts',
                'ai_prompt_tenant',
                'auth_users',
                'billing_payments',
                'billing_invoices',
                'shared_media',
            ]);

            $tenant->forceFill([
                'billing_status' => BillingTenantStatus::PURGED,
                'is_active' => false,
            ])->save();

            $tenant->delete();

            return $report;
        });

        $this->cleanupTenantStorage((string) $tenant->id);
        $this->forgetTenantStatusCache((string) $tenant->id);

        BillingTenantPurgedEvent::dispatch(
            (string) $tenant->id,
            (string) $report->id,
            now()->toIso8601String(),
        );

        if (is_string($tenant->primary_email) && $tenant->primary_email !== '') {
            Mail::to($tenant->primary_email)->queue(new BillingCollectionMail(
                templateId: 'purge_final',
                tenant: $tenant,
                invoice: null,
                daysOverdue: 0,
                purgeReport: $report,
            ));
        }

        return [
            'tenant_id' => (string) $tenant->id,
            'purged' => true,
            'dry_run' => false,
            'report_id' => (string) $report->id,
        ];
    }

    private function assertSafetyChecks(PlatformTenant $tenant): void
    {
        $recentPayment = BillingPayment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('confirmed_at')
            ->where('confirmed_at', '>=', now()->subDays(30))
            ->exists();

        if ($recentPayment) {
            throw new RuntimeException('Tenant possui pagamento nos últimos 30 dias. Purge bloqueado.');
        }

        $hasSuperAdmin = AuthUser::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->contains(static fn (AuthUser $user): bool => $user->isSuperAdmin());

        if ($hasSuperAdmin) {
            throw new RuntimeException('Tenant possui usuário super-admin. Purge bloqueado.');
        }
    }

    /**
     * @param  list<string>  $tables
     */
    private function softDeleteTenantRows(string $tenantId, array $tables): void
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            if (Schema::hasColumn($table, 'deleted_at')) {
                DB::table($table)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table($table)
                ->where('tenant_id', $tenantId)
                ->delete();
        }
    }

    private function cleanupTenantStorage(string $tenantId): void
    {
        Storage::disk(config('filesystems.default'))
            ->deleteDirectory("tenants/{$tenantId}");
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
