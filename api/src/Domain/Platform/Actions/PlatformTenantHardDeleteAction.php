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
    private const PROTECTED_TENANT_DELETE_MESSAGE = 'Empresa principal InteraZap não pode ser excluída.';

    /**
     * Executa o hard delete completo do tenant após validações de segurança.
     *
     * Valida a senha do admin, verifica ausência de pagamentos recentes e de super admins
     * vinculados ao tenant, deleta todos os dados em tabelas relacionadas, remove storage e
     * invalida o cache de status de cobrança.
     *
     * @param  PlatformTenant  $tenant  Tenant a ser purgado.
     * @param  string  $adminPassword  Senha do usuário admin para confirmação.
     * @param  AuthUser  $actor  Usuário que está executando a ação.
     * @return array{tenant_id:string,purged:bool} Resultado da operação.
     *
     * @throws \RuntimeException Quando a senha está incorreta, o tenant é protegido ou possui pagamentos/super admin.
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

    /**
     * Valida a senha do administrador que solicita o purge.
     *
     * @throws \RuntimeException Quando a senha está incorreta.
     */
    private function validateAdminPassword(AuthUser $actor, string $password): void
    {
        if (! Hash::check($password, $actor->password)) {
            throw new RuntimeException('Senha de administrador incorreta.');
        }
    }

    /**
     * Verifica condições de segurança antes de permitir o purge.
     *
     * Bloqueia o purge se: é o tenant protegido principal, tem pagamento nos últimos 30 dias
     * ou possui algum usuário com papel de super admin.
     *
     * @throws \RuntimeException Em qualquer condição de bloqueio.
     */
    private function assertSafetyChecks(PlatformTenant $tenant): void
    {
        if ($tenant->isProtectedDefaultTenant()) {
            throw new RuntimeException(self::PROTECTED_TENANT_DELETE_MESSAGE);
        }

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
     * Deleta os dados do tenant em todas as tabelas relacionadas.
     *
     * Itera sobre a lista de tabelas, verifica existência de cada uma e exclui
     * todos os registros com tenant_id correspondente.
     *
     * @param  string  $tenantId  UUID do tenant.
     */
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

    /**
     * Remove o diretório de armazenamento do tenant do disco configurado.
     *
     * @param  string  $tenantId  UUID do tenant.
     */
    private function cleanupTenantStorage(string $tenantId): void
    {
        Storage::disk(config('filesystems.default'))
            ->deleteDirectory("tenants/{$tenantId}");
    }

    /**
     * Invalida o cache de status de cobrança do tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     */
    private function forgetTenantStatusCache(string $tenantId): void
    {
        $prefix = (string) config('billing.delinquency.cache.billing_status_prefix', 'billing:tenant_status:');
        Cache::forget($prefix.$tenantId);
    }
}
