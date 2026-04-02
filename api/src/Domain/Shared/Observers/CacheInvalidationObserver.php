<?php

declare(strict_types=1);

namespace Domain\Shared\Observers;

use Domain\Chat\Models\ChatQuickAnswer;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Services\CriticalDataCacheService;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer for cache invalidation on model changes.
 *
 * Automatically invalidates cached data when models are updated or deleted.
 */
final class CacheInvalidationObserver
{
    public function __construct(
        private readonly CriticalDataCacheService $cacheService
    ) {}

    /**
     * Handle PlatformTenant events.
     */
    public function updated(Model $model): void
    {
        if ($model instanceof PlatformTenant) {
            $this->cacheService->forgetTenantConfig($model->id);
            $this->cacheService->forgetTenantSubscription($model->id);
        } elseif ($model instanceof PlatformPlan) {
            $this->cacheService->forgetPlanQuotas($model->id);
        }
    }

    /**
     * Handle model saved events (create and update).
     */
    public function saved(Model $model): void
    {
        if ($model instanceof CRMNegotiationFunnel) {
            $this->cacheService->forgetFunnel($model->tenant_id, $model->id);
        } elseif ($model instanceof ChatQuickAnswer) {
            $this->cacheService->forgetQuickAnswers($model->tenant_id);
        }
    }

    /**
     * Handle model deletion.
     */
    public function deleted(Model $model): void
    {
        if ($model instanceof CRMNegotiationFunnel) {
            $this->cacheService->forgetFunnel($model->tenant_id, $model->id);
        } elseif ($model instanceof ChatQuickAnswer) {
            $this->cacheService->forgetQuickAnswers($model->tenant_id);
        }
    }
}
