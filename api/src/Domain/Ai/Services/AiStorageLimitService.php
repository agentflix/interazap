<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AiStorageLimitServiceInterface;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Serviço de gerenciamento de limites de armazenamento da Base de Conhecimento.
 *
 * Contexto: integra-se com PlatformPlan para aplicar cotas de armazenamento por tenant.
 * Limite 0 significa armazenamento ilimitado. Caso não haja plano ativo,
 * aplica o limite padrão de 100 MB (DEFAULT_STORAGE_LIMIT).
 */
final class AiStorageLimitService implements AiStorageLimitServiceInterface
{
    /**
     * Default storage limit if plan not found (100MB).
     */
    private const int DEFAULT_STORAGE_LIMIT = 104857600;

    /**
     * Verifica se o tenant pode fazer upload de um arquivo com o tamanho informado.
     *
     * @param  PlatformTenant  $tenant  Tenant a verificar.
     * @param  int  $fileSize  Tamanho do arquivo em bytes.
     * @return bool True se o upload está dentro do limite, false caso contrário.
     */
    public function canUpload(PlatformTenant $tenant, int $fileSize): bool
    {
        $currentUsage = $this->getCurrentUsage($tenant);
        $limit = $this->getStorageLimit($tenant);

        // Unlimited storage
        if ($limit === 0) {
            return true;
        }

        return ($currentUsage + $fileSize) <= $limit;
    }

    /**
     * Retorna o uso atual de armazenamento do tenant em bytes.
     *
     * @param  PlatformTenant  $tenant  Tenant a consultar.
     * @return int Total de bytes utilizados pelos documentos ativos.
     */
    public function getCurrentUsage(PlatformTenant $tenant): int
    {
        return (int) AiKnowledgeDocument::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->sum('file_size_bytes');
    }

    /**
     * Retorna o limite de armazenamento do plano do tenant em bytes.
     *
     * @param  PlatformTenant  $tenant  Tenant a consultar.
     * @return int Limite em bytes, ou 0 para armazenamento ilimitado.
     */
    public function getStorageLimit(PlatformTenant $tenant): int
    {
        // Try to get plan via subscription
        $plan = $this->getTenantPlan($tenant);

        if (! $plan) {
            return self::DEFAULT_STORAGE_LIMIT;
        }

        // Check if storage is limited
        if (! $plan->isStorageLimited()) {
            return 0; // Unlimited
        }

        return (int) $plan->storage_limit_bytes;
    }

    /**
     * Retorna o armazenamento restante do tenant em bytes.
     *
     * @param  PlatformTenant  $tenant  Tenant a consultar.
     * @return int Bytes disponíveis, ou PHP_INT_MAX para armazenamento ilimitado.
     */
    public function getRemainingStorage(PlatformTenant $tenant): int
    {
        $limit = $this->getStorageLimit($tenant);

        // Unlimited
        if ($limit === 0) {
            return PHP_INT_MAX;
        }

        $usage = $this->getCurrentUsage($tenant);

        return max(0, $limit - $usage);
    }

    /**
     * Retorna o percentual de uso de armazenamento do tenant.
     *
     * @param  PlatformTenant  $tenant  Tenant a consultar.
     * @return float Percentual entre 0.0 e 100.0 (0.0 para ilimitado).
     */
    public function getUsagePercentage(PlatformTenant $tenant): float
    {
        $limit = $this->getStorageLimit($tenant);

        // Unlimited
        if ($limit === 0) {
            return 0.0;
        }

        $usage = $this->getCurrentUsage($tenant);

        return ($usage / $limit) * 100;
    }

    /**
     * Obtém o plano ativo do tenant via billing_subscriptions.
     *
     * Verifica a existência da tabela antes de consultar para evitar
     * que o PostgreSQL aborte a transação quando a tabela não existe.
     *
     * @param  PlatformTenant  $tenant  Tenant a consultar.
     * @return PlatformPlan|null Plano ativo ou null se não encontrado.
     */
    private function getTenantPlan(PlatformTenant $tenant): ?PlatformPlan
    {
        // Check if billing_subscriptions table exists before querying
        // This prevents PostgreSQL from aborting the transaction when table doesn't exist
        if (! Schema::hasTable('billing_subscriptions')) {
            return null;
        }

        $planId = DB::table('billing_subscriptions')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->value('plan_id');

        if (! $planId) {
            return null;
        }

        return PlatformPlan::find($planId);
    }
}
