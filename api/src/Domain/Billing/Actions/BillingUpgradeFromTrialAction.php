<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Carbon\CarbonImmutable;
use Domain\Billing\DTOs\BillingChangePlanDTO;
use Domain\Billing\Models\TenantMessageUsage;
use Domain\Billing\Services\BillingCycleCalculator;
use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Action de upgrade de trial para plano pago via token de cartão.
 *
 * Etapas (em transação DB):
 * 1. Valida que tenant está em plano trial e `has_used_trial=false`
 * 2. Cobra via `createPaymentWithToken` (token Asaas)
 * 3. Se `status=CONFIRMED` → troca plano via `BillingChangePlanAction(bypassPassword=true)`
 * 4. Salva `payment_method_token/brand/last4` em `platform_tenants`
 * 5. Marca `has_used_trial=true`
 * 6. Cria nova linha `tenant_message_usage` com ciclo do novo plano
 * 7. Registra auditoria
 *
 * @throws ValidationException em casos de negócio
 * @throws \RuntimeException se charge falhar
 */
final class BillingUpgradeFromTrialAction
{
    public function __construct(
        private readonly BillingGatewayService $billingGatewayService,
        private readonly BillingChangePlanAction $changePlanAction,
        private readonly BillingCycleCalculator $cycleCalculator,
    ) {}

    /**
     * Executa o upgrade de trial para plano pago com cobrança via token de cartão.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $userId  UUID do usuário que solicitou o upgrade
     * @param  string  $planId  UUID do plano de destino (não-trial)
     * @param  string  $cardToken  Token Asaas do cartão para cobrança
     * @return array{success: bool, new_plan: array<string,mixed>, next_billing_date: string}
     *
     * @throws \Illuminate\Validation\ValidationException Quando as pré-condições de negócio não são atendidas
     * @throws \RuntimeException Quando a cobrança é recusada pelo gateway
     */
    public function execute(string $tenantId, string $userId, string $planId, string $cardToken): array
    {
        $newPlan = PlatformPlan::query()
            ->where('id', $planId)
            ->where('is_active', true)
            ->where('is_trial', false)
            ->firstOrFail();

        return DB::transaction(function () use ($tenantId, $userId, $newPlan, $cardToken): array {
            /** @var PlatformTenant $tenant */
            $tenant = PlatformTenant::query()
                ->with('plan')
                ->where('id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            // Validate trial eligibility
            if (! $tenant->plan || ! $tenant->plan->is_trial) {
                throw ValidationException::withMessages([
                    'plan' => ['O tenant não está em plano trial.'],
                ]);
            }

            if ($tenant->has_used_trial) {
                throw ValidationException::withMessages([
                    'trial' => ['O trial já foi utilizado para upgrade anteriormente.'],
                ]);
            }
            // Ensure Asaas customer exists
            $customerId = $tenant->asaas_customer_id;
            if (! $customerId) {
                $customerId = $this->billingGatewayService->ensureCustomer($tenant);
            }

            if (! $customerId) {
                throw new \RuntimeException('Não foi possível criar o cliente no gateway de pagamento.');
            }

            // Charge via saved token
            $chargeResult = $this->billingGatewayService->createPaymentWithToken(
                customerId: $customerId,
                cardToken: $cardToken,
                amount: (float) $newPlan->price_monthly,
                metadata: [
                    'description' => "Assinatura InteraZap — Plano {$newPlan->name}",
                    'external_reference' => $tenant->id,
                ]
            );

            if ($chargeResult['paymentId'] === null) {
                throw new \RuntimeException($this->billingGatewayService->getLastError() ?? 'Cobrança recusada pelo gateway.');
            }

            if (! in_array($chargeResult['status'], ['CONFIRMED', 'RECEIVED'], true)) {
                $userMessage = $chargeResult['status'] === 'PENDING'
                    ? 'Pagamento em análise pelo banco. Aguarde alguns minutos e tente novamente.'
                    : 'Pagamento não autorizado. Status: '.($chargeResult['status'] ?? 'desconhecido');

                throw ValidationException::withMessages(['payment' => [$userMessage]]);
            }

            // Change plan (bypasses password validation)
            $this->changePlanAction->execute(
                tenantId: $tenant->id,
                userId: $userId,
                dto: new BillingChangePlanDTO(
                    planId: $newPlan->id,
                    currentPassword: null,
                    bypassPassword: true,
                )
            );

            // Save payment method token
            $tenant->forceFill([
                'payment_method_token' => $cardToken,
                'payment_method_brand' => $chargeResult['brand'],
                'payment_method_last4' => $chargeResult['last4'],
                'has_used_trial' => true,
            ])->save();

            // Create new usage row for the new plan's cycle
            $now = CarbonImmutable::now();
            $anchorDay = $tenant->billing_cycle_anchor_day ?? 1;
            // New plan is never trial; use monthly anchor (null) for regular billing cycle
            $cycle = $this->cycleCalculator->calculate(
                anchorDay: $anchorDay,
                reference: $now,
                cycleDays: $newPlan->is_trial ? $newPlan->cycle_days : null,
            );

            // Reset or create usage row for the new plan's cycle
            TenantMessageUsage::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'cycle_start' => $cycle['cycle_start']->toDateString()],
                [
                    'cycle_end' => $cycle['cycle_end']->toDateString(),
                    'message_count' => 0,
                    'overage_count' => 0,
                ]
            );

            Log::info('billing.upgrade_from_trial.success', [
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
                'plan_id' => $newPlan->id,
                'payment_id' => $chargeResult['paymentId'],
            ]);

            return [
                'success' => true,
                'new_plan' => [
                    'id' => $newPlan->id,
                    'name' => $newPlan->name,
                    'slug' => $newPlan->slug,
                    'price_monthly' => $newPlan->price_monthly,
                ],
                'next_billing_date' => $cycle['cycle_end']->toDateString(),
            ];
        });
    }
}
