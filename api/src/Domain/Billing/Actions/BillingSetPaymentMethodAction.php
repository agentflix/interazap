<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Valida e persiste um novo método de pagamento (cartão tokenizado) para o tenant.
 *
 * Não faz cobrança — apenas valida o token via gateway e atualiza os metadados.
 */
final class BillingSetPaymentMethodAction
{
    public function __construct(
        private readonly BillingGatewayService $billingGatewayService,
    ) {}

    /**
     * Valida o token de cartão no gateway e persiste os metadados no tenant.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $cardToken  Token Asaas do cartão a ser validado
     * @return array{brand: string|null, last4: string|null}
     *
     * @throws \Illuminate\Validation\ValidationException Quando o gateway rejeitar o token
     */
    public function execute(string $tenantId, string $cardToken): array
    {
        $tenant = PlatformTenant::query()
            ->where('id', $tenantId)
            ->firstOrFail();

        $customerId = $tenant->asaas_customer_id;
        if (! $customerId) {
            $customerId = $this->billingGatewayService->ensureCustomer($tenant);
        }

        if (! $customerId) {
            throw ValidationException::withMessages([
                'payment_method' => ['Não foi possível validar o cartão. Tente novamente.'],
            ]);
        }

        $methodData = $this->billingGatewayService->getPaymentMethod($customerId, $cardToken);

        if (! $methodData['brand'] && ! $methodData['last4']) {
            throw ValidationException::withMessages([
                'card_token' => ['Token de cartão inválido ou expirado.'],
            ]);
        }

        $tenant->forceFill([
            'payment_method_token' => $cardToken,
            'payment_method_brand' => $methodData['brand'],
            'payment_method_last4' => $methodData['last4'],
        ])->save();

        Log::info('billing.payment_method.updated', [
            'tenant_id' => $tenantId,
            'brand' => $methodData['brand'],
        ]);

        return $methodData;
    }
}
