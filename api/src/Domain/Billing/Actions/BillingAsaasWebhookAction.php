<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Domain\Billing\DTOs\BillingWebhookDTO;
use Domain\Billing\Enums\BillingEventType;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Enums\BillingPaymentStatus;
use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Models\BillingPayment;
use Domain\Billing\Repositories\BillingWebhookEventRepository;
use Domain\Billing\Services\WebhookSignatureValidator;
use Domain\Configuration\Events\BillingPaymentConfirmedEvent;
use Domain\Configuration\Events\BillingPaymentOverdueEvent;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Processa webhook Asaas: idempotência, atualização de fatura e registro de pagamento.
 */
final class BillingAsaasWebhookAction
{
    public function __construct(
        private readonly BillingWebhookEventRepository $repository,
        private readonly BillingUnlockTenantAction $unlockTenantAction,
        private readonly WebhookSignatureValidator $signatureValidator,
    ) {}

    /**
     * Processa evento de webhook do Asaas.
     *
     * @param  bool  $skipIdempotency  Apenas para uso em testes unitários. Em produção, a idempotência é sempre aplicada.
     * @param  Request|null  $request  Request HTTP opcional para validar assinatura/token do provider.
     * @return array{created: bool, invoice_updated: bool}
     */
    public function handle(PlatformTenant $tenant, BillingWebhookDTO $dto, bool $skipIdempotency = false, ?Request $request = null): array
    {
        if ($request !== null && ! $this->signatureValidator->validate($request, $dto->provider)) {
            throw ValidationException::withMessages([
                'signature' => ['Assinatura do webhook inválida.'],
            ]);
        }

        // skipIdempotency só é respeitado em ambiente de testes — nunca em produção.
        $skipIdempotency = $skipIdempotency && app()->runningUnitTests();

        // Idempotência: verifica se já processamos este evento (insertOrIgnore — atômico na DB).
        $created = true;

        if (! $skipIdempotency) {
            $created = $this->repository->storeIfNotExists($tenant, $dto);

            if (! $created) {
                Log::info('Asaas webhook: evento duplicado ignorado', [
                    'tenant_id' => $tenant->id,
                    'event_type' => $dto->eventType,
                    'provider_event_id' => $dto->providerEventId,
                ]);

                return ['created' => false, 'invoice_updated' => false];
            }
        }

        $invoiceUpdated = false;

        // Processa eventos de pagamento
        if ($this->isPaymentConfirmedEvent($dto->eventType)) {
            $invoiceUpdated = $this->handlePaymentConfirmed($tenant, $dto);
        }

        if ($dto->eventType === BillingEventType::PAYMENT_OVERDUE->value) {
            $invoiceUpdated = $this->handlePaymentOverdue($tenant, $dto) || $invoiceUpdated;
        }

        if ($this->isPaymentReversalEvent($dto->eventType)) {
            $invoiceUpdated = $this->handlePaymentReversed($tenant, $dto) || $invoiceUpdated;
        }

        // Dispara evento interno para outros listeners
        Event::dispatch('billing.webhook_received', [
            'tenant_id' => $tenant->id,
            'event_type' => $dto->eventType,
            'provider_event_id' => $dto->providerEventId,
            'payload' => $dto->payload,
        ]);

        return ['created' => $created, 'invoice_updated' => $invoiceUpdated];
    }

    /**
     * Verifica se o evento indica confirmação de pagamento.
     */
    private function isPaymentConfirmedEvent(string $eventType): bool
    {
        return in_array($eventType, [
            BillingEventType::PAYMENT_RECEIVED->value,
            BillingEventType::PAYMENT_CONFIRMED->value,
        ], true);
    }

    /**
     * Verifica se o evento implica reversão de um pagamento previamente confirmado
     * (refund, chargeback, exclusão ou estorno).
     */
    private function isPaymentReversalEvent(string $eventType): bool
    {
        return in_array($eventType, [
            BillingEventType::PAYMENT_REFUNDED->value,
            BillingEventType::PAYMENT_CHARGEBACK_REQUESTED->value,
            BillingEventType::PAYMENT_CHARGEBACK_DISPUTE->value,
            BillingEventType::PAYMENT_AWAITING_CHARGEBACK_REVERSAL->value,
            BillingEventType::PAYMENT_REVERSED->value,
            BillingEventType::PAYMENT_DELETED->value,
        ], true);
    }

    /**
     * Processa confirmação de pagamento e atualiza a fatura.
     */
    private function handlePaymentConfirmed(PlatformTenant $tenant, BillingWebhookDTO $dto): bool
    {
        $paymentData = $dto->payload['payment'] ?? $dto->payload;
        $externalReference = $paymentData['externalReference'] ?? null;
        $asaasPaymentId = $paymentData['id'] ?? null;
        $hasExternalReference = is_string($externalReference) && $externalReference !== '';
        $hasAsaasPaymentId = is_string($asaasPaymentId) && $asaasPaymentId !== '';

        if (! $hasExternalReference && ! $hasAsaasPaymentId) {
            Log::warning('Asaas webhook: sem referência para identificar fatura', [
                'tenant_id' => $tenant->id,
                'payload' => $dto->payload,
            ]);

            return false;
        }

        // Busca a fatura pela referência externa (nosso ID) ou pelo ID do pagamento Asaas
        $invoice = BillingInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->when($hasExternalReference, fn ($q) => $q->where('id', $externalReference))
            ->when(! $hasExternalReference, fn ($q) => $q->where('asaas_payment_id', $asaasPaymentId))
            ->first();

        if (! $invoice) {
            Log::warning('Asaas webhook: fatura não encontrada', [
                'tenant_id' => $tenant->id,
                'external_reference' => $externalReference,
                'asaas_payment_id' => $asaasPaymentId,
            ]);

            return false;
        }

        return DB::transaction(function () use ($invoice, $paymentData, $tenant) {
            $providerPaymentId = is_string($paymentData['id'] ?? null) && $paymentData['id'] !== ''
                ? (string) $paymentData['id']
                : null;

            $wasAlreadyPaid = $invoice->status === BillingInvoiceStatus::PAID;

            $hasConfirmedPayment = BillingPayment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', BillingPaymentStatus::CONFIRMED)
                ->when(
                    $providerPaymentId !== null,
                    static fn ($query) => $query->where('provider_payment_id', $providerPaymentId)
                )
                ->exists();

            if (! $hasConfirmedPayment && $providerPaymentId !== null) {
                $hasConfirmedPayment = BillingPayment::query()
                    ->where('provider', 'asaas')
                    ->where('provider_payment_id', $providerPaymentId)
                    ->where('status', BillingPaymentStatus::CONFIRMED)
                    ->exists();
            }

            // Atualiza a fatura como paga
            if (! $wasAlreadyPaid) {
                $invoice->update([
                    'status' => BillingInvoiceStatus::PAID,
                    'paid_at' => now(),
                ]);
            }

            // Registra o pagamento
            if (! $hasConfirmedPayment) {
                BillingPayment::create([
                    'tenant_id' => $invoice->tenant_id,
                    'invoice_id' => $invoice->id,
                    'amount' => (float) ($paymentData['value'] ?? $invoice->amount),
                    'payment_method' => $this->normalizePaymentMethod($paymentData['billingType'] ?? 'UNDEFINED'),
                    'provider' => 'asaas',
                    'provider_payment_id' => $providerPaymentId ?? 'unknown',
                    'status' => BillingPaymentStatus::CONFIRMED,
                    'confirmed_at' => now(),
                    'metadata' => $paymentData,
                ]);
            }

            Log::info('Asaas webhook: fatura marcada como paga', [
                'invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenant_id,
            ]);

            if (! $wasAlreadyPaid) {
                BillingPaymentConfirmedEvent::dispatch(
                    (string) $invoice->tenant_id,
                    (string) $invoice->id,
                    (float) $invoice->amount,
                    (string) $invoice->reference_month,
                );
            }

            $tenant->refresh();
            if (in_array($tenant->billing_status, [
                BillingTenantStatus::LOCKED,
                BillingTenantStatus::PENDING_PURGE,
            ], true)) {
                $this->unlockTenantAction->handle($tenant);
            }

            return true;
        });
    }

    /**
     * Processa eventos de fatura vencida.
     */
    private function handlePaymentOverdue(PlatformTenant $tenant, BillingWebhookDTO $dto): bool
    {
        $paymentData = $dto->payload['payment'] ?? $dto->payload;
        $externalReference = $paymentData['externalReference'] ?? null;
        $asaasPaymentId = $paymentData['id'] ?? null;
        $hasExternalReference = is_string($externalReference) && $externalReference !== '';
        $hasAsaasPaymentId = is_string($asaasPaymentId) && $asaasPaymentId !== '';

        if (! $hasExternalReference && ! $hasAsaasPaymentId) {
            return false;
        }

        $invoice = BillingInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->when($hasExternalReference, fn ($q) => $q->where('id', $externalReference))
            ->when(! $hasExternalReference, fn ($q) => $q->where('asaas_payment_id', $asaasPaymentId))
            ->first();

        if (! $invoice || $invoice->status === BillingInvoiceStatus::PAID) {
            return false;
        }

        $invoice->update(['status' => BillingInvoiceStatus::OVERDUE]);

        BillingPaymentOverdueEvent::dispatch(
            (string) $invoice->tenant_id,
            (string) $invoice->id,
            (float) $invoice->amount,
            (string) $invoice->reference_month,
        );

        return true;
    }

    /**
     * Reverte uma fatura previamente paga após refund/chargeback/exclusão.
     *
     * - Marca o pagamento confirmado correspondente como REFUNDED.
     * - Se não restar nenhum pagamento confirmado, devolve a fatura ao estado
     *   apropriado (OVERDUE se já passou do vencimento; caso contrário PENDING).
     */
    private function handlePaymentReversed(PlatformTenant $tenant, BillingWebhookDTO $dto): bool
    {
        $paymentData = $dto->payload['payment'] ?? $dto->payload;
        $externalReference = $paymentData['externalReference'] ?? null;
        $asaasPaymentId = $paymentData['id'] ?? null;
        $hasExternalReference = is_string($externalReference) && $externalReference !== '';
        $hasAsaasPaymentId = is_string($asaasPaymentId) && $asaasPaymentId !== '';

        if (! $hasExternalReference && ! $hasAsaasPaymentId) {
            Log::warning('Asaas webhook: reversão sem referência para identificar fatura', [
                'tenant_id' => $tenant->id,
                'event_type' => $dto->eventType,
                'payload' => $dto->payload,
            ]);

            return false;
        }

        $invoice = BillingInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->when($hasExternalReference, fn ($q) => $q->where('id', $externalReference))
            ->when(! $hasExternalReference, fn ($q) => $q->where('asaas_payment_id', $asaasPaymentId))
            ->first();

        if (! $invoice) {
            Log::warning('Asaas webhook: reversão — fatura não encontrada', [
                'tenant_id' => $tenant->id,
                'event_type' => $dto->eventType,
                'external_reference' => $externalReference,
                'asaas_payment_id' => $asaasPaymentId,
            ]);

            return false;
        }

        return DB::transaction(function () use ($invoice, $paymentData, $hasAsaasPaymentId, $asaasPaymentId, $dto) {
            $providerPaymentId = $hasAsaasPaymentId ? (string) $asaasPaymentId : null;

            $paymentQuery = BillingPayment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', BillingPaymentStatus::CONFIRMED);

            if ($providerPaymentId !== null) {
                $paymentQuery->where('provider_payment_id', $providerPaymentId);
            }

            $payment = $paymentQuery->first();

            if ($payment !== null) {
                $payment->update([
                    'status' => BillingPaymentStatus::REFUNDED,
                    'metadata' => array_merge((array) ($payment->metadata ?? []), [
                        'reversal_event' => $dto->eventType,
                        'reversal_payload' => $paymentData,
                        'reversed_at' => now()->toISOString(),
                    ]),
                ]);
            }

            $hasOtherConfirmed = BillingPayment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', BillingPaymentStatus::CONFIRMED)
                ->exists();

            if (! $hasOtherConfirmed) {
                $newStatus = $invoice->due_date !== null && $invoice->due_date->isPast()
                    ? BillingInvoiceStatus::OVERDUE
                    : BillingInvoiceStatus::PENDING;

                $invoice->update([
                    'status' => $newStatus,
                    'paid_at' => null,
                ]);
            }

            Log::info('Asaas webhook: pagamento revertido', [
                'invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenant_id,
                'event_type' => $dto->eventType,
                'provider_payment_id' => $providerPaymentId,
            ]);

            return true;
        });
    }

    /**
     * Normaliza o tipo de pagamento do Asaas.
     */
    private function normalizePaymentMethod(string $billingType): string
    {
        return match (strtoupper($billingType)) {
            'PIX' => 'pix',
            'CREDIT_CARD' => 'credit_card',
            'BOLETO' => 'boleto',
            default => 'other',
        };
    }
}
