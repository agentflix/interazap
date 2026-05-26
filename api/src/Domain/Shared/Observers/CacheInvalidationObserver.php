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
 * Observer para invalidação de cache em mudanças de modelos críticos.
 *
 * Invalida automaticamente os dados em cache quando PlatformTenant, PlatformPlan,
 * CRMNegotiationFunnel ou ChatQuickAnswer são salvos ou excluídos.
 */
final class CacheInvalidationObserver
{
    public function __construct(
        private readonly CriticalDataCacheService $cacheService
    ) {}

    /**
     * Invalida cache de configuração e assinatura quando tenant ou plano é atualizado.
     *
     * @param  Model  $model  Instância do modelo atualizado.
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
     * Invalida cache de funil ou respostas rápidas quando o modelo é salvo.
     *
     * @param  Model  $model  Instância do modelo criado ou atualizado.
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
     * Invalida cache de funil ou respostas rápidas quando o modelo é excluído.
     *
     * @param  Model  $model  Instância do modelo excluído.
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
