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

    /**
     * Cria um novo plano, gera o slug automaticamente se ausente e registra o produto no gateway de cobrança.
     *
     * @param  PlatformPlanDTO  $dto  Dados do plano a ser criado.
     * @return PlatformPlan Plano criado e persistido.
     */
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
