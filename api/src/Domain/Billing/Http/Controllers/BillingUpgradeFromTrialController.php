<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Controllers;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Actions\BillingUpgradeFromTrialAction;
use Domain\Billing\Http\Requests\BillingUpgradeFromTrialRequest;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Controller para upgrade de trial para plano pago.
 *
 * Autenticado via `auth:sanctum`. Apenas owners/inquilinos podem executar upgrade.
 */
final class BillingUpgradeFromTrialController extends BaseController
{
    public function __construct(
        private readonly BillingUpgradeFromTrialAction $upgradeAction,
    ) {}

    /**
     * Executar upgrade de trial para plano pago via token de cartão.
     *
     * @param  BillingUpgradeFromTrialRequest  $request  Payload validado (plan_id, card_token)
     * @return JsonResponse `{success, new_plan, next_billing_date}`
     */
    public function store(BillingUpgradeFromTrialRequest $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();

        $result = $this->upgradeAction->execute(
            tenantId: $this->tenantId($request),
            userId: (string) $user->id,
            planId: $request->string('plan_id')->toString(),
            cardToken: $request->string('card_token')->toString(),
        );

        return $this->success($result, 'Upgrade realizado com sucesso');
    }
}
