<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Models\BillingPurgeReport;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gera snapshot de exclusão para compliance LGPD antes do purge.
 */
final class BillingGeneratePurgeReportAction
{
    public function handle(PlatformTenant $tenant): BillingPurgeReport
    {
        $summary = [
            'users' => $this->countTenantRows('auth_users', (string) $tenant->id),
            'instances' => $this->countTenantRows('chat_instances', (string) $tenant->id),
            'negotiations' => $this->countTenantRows('crm_negotiations', (string) $tenant->id),
            'contacts' => $this->countTenantRows('crm_contacts', (string) $tenant->id),
            'files' => $this->countTenantRows('shared_media', (string) $tenant->id),
            'storage_bytes' => 0,
        ];

        $invoicesSnapshot = BillingInvoice::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->get()
            ->map(static fn (BillingInvoice $invoice): array => [
                'id' => (string) $invoice->id,
                'reference_month' => (string) $invoice->reference_month,
                'amount' => (float) $invoice->amount,
                'status' => (string) $invoice->status->value,
                'due_date' => (string) $invoice->due_date,
                'paid_at' => $invoice->paid_at?->toIso8601String(),
            ])
            ->all();

        return BillingPurgeReport::query()->create([
            'tenant_id' => (string) $tenant->id,
            'tenant_name' => (string) $tenant->name,
            'tenant_document' => is_string($tenant->document) ? $tenant->document : null,
            'tenant_email' => is_string($tenant->primary_email) ? $tenant->primary_email : null,
            'summary' => $summary,
            'invoices_snapshot' => $invoicesSnapshot,
            'purged_at' => now(),
            'purged_by' => 'system',
        ]);
    }

    private function countTenantRows(string $table, string $tenantId): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            return 0;
        }

        return (int) DB::table($table)->where('tenant_id', $tenantId)->count();
    }
}
