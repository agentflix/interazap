<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\DTOs\PlatformPlanDTO;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Support\Str;

/**
 * Action para criação de planos da plataforma.
 */
final class CreatePlatformPlanAction
{
    public function __construct(
        private readonly BillingGatewayService $gatewayService,
    ) {}

    public function execute(PlatformPlanDTO $dto): PlatformPlan
    {
        $payload = $dto->toArray();

        if (! $payload['slug'] && $payload['name']) {
            $payload['slug'] = Str::slug($payload['name']);
        }

        if ($payload['is_active'] === null) {
            unset($payload['is_active']);
        }

        $plan = PlatformPlan::query()->create($payload);

        $productId = $this->gatewayService->createProduct($plan);
        if ($productId) {
            $plan->forceFill(['asaas_product_id' => $productId])->save();
        }

        return $plan->refresh();
    }
}
