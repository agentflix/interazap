<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Controllers;

use Domain\Billing\Actions\BillingSetPaymentMethodAction;
use Domain\Billing\Http\Requests\BillingPaymentMethodRequest;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Controller para gestão do método de pagamento tokenizado do tenant.
 */
final class BillingPaymentMethodController extends BaseController
{
    public function __construct(
        private readonly BillingSetPaymentMethodAction $setPaymentMethodAction,
    ) {}

    /**
     * Atualizar método de pagamento com novo token de cartão.
     *
     * @param  BillingPaymentMethodRequest  $request  Payload validado com `card_token`
     * @return JsonResponse `{brand, last4}`
     */
    public function store(BillingPaymentMethodRequest $request): JsonResponse
    {
        $result = $this->setPaymentMethodAction->execute(
            tenantId: $this->tenantId($request),
            cardToken: $request->string('card_token')->toString(),
        );

        return $this->success($result, 'Método de pagamento atualizado');
    }
}
