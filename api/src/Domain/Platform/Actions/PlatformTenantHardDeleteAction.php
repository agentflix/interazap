<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Models\BillingPayment;
use Domain\Platform\Events\PlatformTenantPurgedEvent;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Executa hard delete completo de tenant com validação de senha admin e safeguards.
 */
final class PlatformTenantHardDeleteAction
{
    /**
     * @return array{tenant_id:string,purged:bool}
     */
    public function execute(PlatformTenant $tenant, string $adminPassword, AuthUser $actor): array
    {
        $this->validateAdminPassword($actor, $adminPassword);
        $this->assertSafetyChecks($tenant);

        $tenantId = (string) $tenant->id;

        DB::transaction(function () use ($tenant, $tenantId): void {
            $this->deleteTenantData($tenantId);

            $tenant->forceDelete();
        });

        $this->cleanupTenantStorage($tenantId);
        $this->forgetTenantStatusCache($tenantId);

        PlatformTenantPurgedEvent::dispatch($tenantId, now()->toIso8601String());

        return [
            'tenant_id' => $tenantId,
            'purged' => true,
        ];
    }

    private function validateAdminPassword(AuthUser $actor, string $password): void
    {
        if (! Hash::check($password, $actor->password)) {
            throw new RuntimeException('Senha de administrador incorreta.');
        }
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

    private function deleteTenantData(string $tenantId): void
    {
        $tables = [
            'ai_usage_logs',
            'ai_seller_notifications',
            'ai_autopilot_runs',
            'ai_autopilot_playbooks',
            'ai_agents',
            'ai_knowledge_chunks',
            'ai_knowledge_documents',
            'chat_messages',
            'chat_tickets',
            'chat_instances',
            'chat_webhook_events',
            'crm_notes',
            'crm_negotiation_tasks',
            'crm_proposals',
            'crm_negotiations',
            'crm_events',
            'crm_products',
            'crm_negotiation_funnel_steps',
            'crm_negotiation_funnels',
            'crm_contacts',
            'crm_companies',
            'billing_payments',
            'billing_invoices',
            'billing_collection_logs',
            'billing_purge_reports',
            'shared_media',
            'auth_device_tokens',
            'auth_users',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            DB::table($table)->where('tenant_id', $tenantId)->delete();
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
}
