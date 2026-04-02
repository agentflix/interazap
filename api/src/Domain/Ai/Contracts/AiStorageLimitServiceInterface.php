<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Platform\Models\PlatformTenant;

/**
 * Interface for storage limit service.
 */
interface AiStorageLimitServiceInterface
{
    /**
     * Check if tenant can upload a file of given size.
     */
    public function canUpload(PlatformTenant $tenant, int $fileSize): bool;

    /**
     * Get current storage usage for tenant.
     */
    public function getCurrentUsage(PlatformTenant $tenant): int;

    /**
     * Get storage limit for tenant's plan.
     */
    public function getStorageLimit(PlatformTenant $tenant): int;
}
