<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Domain\Billing\DTOs\BillingChangePlanDTO;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Executa troca de plano (upgrade/downgrade) com idempotência e efeitos financeiros.
 */
final class BillingChangePlanAction
{
    public function __construct(
        private readonly BillingPlanChangePreviewAction $previewAction,
        private readonly BillingDowngradeEnforcementAction $downgradeEnforcementAction,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $tenantId, string $userId, BillingChangePlanDTO $dto): array
    {
        $idempotencyKey = $this->buildIdempotencyKey($tenantId, $userId, $dto->planId);

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($idempotencyKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = DB::transaction(function () use ($tenantId, $userId, $dto): array {
            $tenant = PlatformTenant::query()
                ->withoutGlobalScopes()
                ->where('id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $user = \Domain\Auth\Models\AuthUser::query()->where('id', $userId)->firstOrFail();

            if (! Hash::check((string) $dto->currentPassword, (string) $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['A senha atual está incorreta.'],
                ]);
            }

            $preview = $this->previewAction->execute($tenantId, $dto->planId);

            $newPlan = PlatformPlan::query()->where('id', $dto->planId)->where('is_active', true)->firstOrFail();

            $tenant->plan_id = $newPlan->id;
            $tenant->save();

            if ($preview['type'] === 'upgrade') {
                $charge = (float) ($preview['financial']['pro_rata_charge'] ?? 0.0);

                $invoice = null;
                if ($charge > 0) {
                    $referenceMonth = now()->format('Y-m');
                    $planChangeMetadata = [
                        'type' => 'plan_change_upgrade',
                        'days_remaining' => $preview['financial']['days_remaining'] ?? null,
                        'days_in_month' => $preview['financial']['days_in_month'] ?? null,
                        'formula' => $preview['financial']['formula'] ?? null,
                    ];

                    $invoice = BillingInvoice::query()
                        ->where('tenant_id', $tenantId)
                        ->where('reference_month', $referenceMonth)
                        ->lockForUpdate()
                        ->first();

                    if ($invoice === null) {
                        $invoice = BillingInvoice::query()->create([
                            'tenant_id' => $tenantId,
                            'plan_id' => $newPlan->id,
                            'reference_month' => $referenceMonth,
                            'amount' => $charge,
                            'status' => BillingInvoiceStatus::PENDING,
                            'due_date' => now()->toDateString(),
                            'metadata' => $planChangeMetadata,
                        ]);
                    } else {
                        $existingMetadata = is_array($invoice->metadata) ? $invoice->metadata : [];
                        $invoice->plan_id = $newPlan->id;
                        $invoice->amount = round((float) $invoice->amount + $charge, 2);
                        $invoice->metadata = array_merge($existingMetadata, $planChangeMetadata);
                        $invoice->save();
                    }
                }

                return [
                    'type' => 'upgrade',
                    'new_plan' => [
                        'id' => $newPlan->id,
                        'name' => $newPlan->name,
                        'price_monthly' => number_format((float) $newPlan->price_monthly, 2, '.', ''),
                    ],
                    'pro_rata_invoice' => $invoice ? [
                        'id' => $invoice->id,
                        'amount' => number_format((float) $invoice->amount, 2, '.', ''),
                        'due_date' => $invoice->due_date->format('Y-m-d'),
                        'status' => $invoice->status->value,
                    ] : null,
                    'affected_resources' => [],
                    'message' => $invoice
                        ? sprintf('Upgrade para %s realizado! Fatura de R$ %s gerada.', $newPlan->name, number_format((float) $invoice->amount, 2, ',', '.'))
                        : sprintf('Upgrade para %s realizado! Sem cobrança pro-rata.', $newPlan->name),
                ];
            }

            $credit = (float) ($preview['financial']['pro_rata_credit'] ?? 0.0);

            $enforcement = $this->downgradeEnforcementAction->execute($tenantId, $newPlan);

            $result = [
                'type' => 'downgrade',
                'new_plan' => [
                    'id' => $newPlan->id,
                    'name' => $newPlan->name,
                    'price_monthly' => number_format((float) $newPlan->price_monthly, 2, '.', ''),
                ],
                'pro_rata_credit' => number_format($credit, 2, '.', ''),
                'affected_resources' => $enforcement,
                'message' => sprintf(
                    'Plano alterado para %s. %d usuários e %d instâncias foram desativados. Crédito de R$ %s na próxima fatura.',
                    $newPlan->name,
                    $enforcement['users_deactivated'],
                    $enforcement['instances_deactivated'],
                    number_format($credit, 2, ',', '.')
                ),
            ];

            if ($credit > 0) {
                $nextInvoice = BillingInvoice::query()
                    ->where('tenant_id', $tenantId)
                    ->whereDate('due_date', '>=', now()->toDateString())
                    ->orderBy('due_date')
                    ->first();

                if ($nextInvoice !== null) {
                    $metadata = is_array($nextInvoice->metadata) ? $nextInvoice->metadata : [];
                    $metadata['plan_change_credit'] = number_format($credit, 2, '.', '');
                    $nextInvoice->metadata = $metadata;
                    $nextInvoice->save();
                }
            }

            return $result;
        });

        Cache::put($idempotencyKey, $result, 30);

        return $result;
    }

    private function buildIdempotencyKey(string $tenantId, string $userId, string $planId): string
    {
        return sprintf('billing:plan-change:%s', sha1($tenantId.'|'.$userId.'|'.$planId));
    }
}
