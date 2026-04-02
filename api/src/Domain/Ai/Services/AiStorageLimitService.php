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
 * Service for managing storage limits for Knowledge Base.
 *
 * Integrates with Platform Plans to enforce storage quotas.
 */
final class AiStorageLimitService implements AiStorageLimitServiceInterface
{
    /**
     * Default storage limit if plan not found (100MB).
     */
    private const int DEFAULT_STORAGE_LIMIT = 104857600;

    /**
     * Check if tenant can upload a file of given size.
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
     * Get current storage usage for tenant.
     */
    public function getCurrentUsage(PlatformTenant $tenant): int
    {
        return (int) AiKnowledgeDocument::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->sum('file_size_bytes');
    }

    /**
     * Get storage limit for tenant's plan.
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
     * Get remaining storage for tenant.
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
     * Get storage usage percentage.
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
     * Get tenant's active plan.
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
