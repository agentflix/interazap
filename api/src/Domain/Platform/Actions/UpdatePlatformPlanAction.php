<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\DTOs\PlatformPlanDTO;
use Domain\Platform\Models\PlatformPlan;

/**
 * Action para atualização de planos da plataforma.
 */
final class UpdatePlatformPlanAction
{
    public function __construct(
        private readonly BillingGatewayService $gatewayService,
    ) {}

    /**
     * Atualiza os dados do plano e sincroniza com o gateway de cobrança quando preço ou nome mudam.
     *
     * @param  PlatformPlan  $plan  Plano a ser atualizado.
     * @param  PlatformPlanDTO  $dto  Novos dados do plano.
     * @return PlatformPlan Plano atualizado.
     */
    public function execute(PlatformPlan $plan, PlatformPlanDTO $dto): PlatformPlan
    {
        $payload = $dto->toArray();

        if (array_key_exists('is_active', $payload) && $payload['is_active'] === null) {
            unset($payload['is_active']);
        }

        if (array_key_exists('slug', $payload) && ($payload['slug'] === null || $payload['slug'] === '')) {
            unset($payload['slug']);
        }

        $priceChanged = (float) $plan->price_monthly !== (float) $payload['price_monthly'];
        $nameChanged = $plan->name !== $payload['name'];

        $plan->update($payload);

        if ($priceChanged || $nameChanged) {
            $this->gatewayService->updateProduct($plan);
        }

        return $plan->refresh();
    }
}
