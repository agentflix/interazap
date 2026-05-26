<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Brick\Money\Money;
use Domain\Billing\DTOs\BillingPaymentDTO;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Enums\BillingPaymentStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Models\BillingPayment;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Action para processar pagamentos com idempotência.
 *
 * Garante que pagamentos não sejam processados em duplicidade
 * utilizando cache com chave baseada em tenant_id + invoice_id + amount + date.
 */
final class ProcessPaymentAction
{
    /** TTL da chave de idempotência no cache (24 horas em segundos). */
    private const IDEMPOTENCY_TTL = 86400;

    /** Prefixo das chaves de idempotência no cache. */
    private const CACHE_PREFIX = 'payment_idempotent';

    /**
     * Processa um pagamento com verificação de idempotência.
     *
     * @param  PlatformTenant  $tenant  Tenant do pagamento
     * @param  BillingPaymentDTO  $dto  Dados do pagamento
     * @param  bool  $skipIdempotency  Pular verificação (para testes)
     * @return array{created: bool, payment: BillingPayment|null, reason: string|null}
     */
    public function handle(
        PlatformTenant $tenant,
        BillingPaymentDTO $dto,
        bool $skipIdempotency = false
    ): array {
        $idempotencyKey = $this->generateIdempotencyKey($tenant, $dto);

        // Verificar se já foi processado
        if (! $skipIdempotency && $this->hasBeenProcessed($idempotencyKey)) {
            $cachedPaymentId = $this->getCachedPaymentId($idempotencyKey);

            Log::info('[ProcessPaymentAction] Pagamento duplicado detectado', [
                'tenant_id' => $tenant->id,
                'invoice_id' => $dto->invoiceId,
                'amount' => $dto->amount,
                'idempotency_key' => $idempotencyKey,
                'cached_payment_id' => $cachedPaymentId,
            ]);

            $existingPayment = $cachedPaymentId
                ? BillingPayment::find($cachedPaymentId)
                : null;

            return [
                'created' => false,
                'payment' => $existingPayment,
                'reason' => 'duplicate_payment',
            ];
        }

        // Verificar se a fatura existe
        $invoice = BillingInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', $dto->invoiceId)
            ->first();

        if (! $invoice) {
            Log::warning('[ProcessPaymentAction] Fatura não encontrada', [
                'tenant_id' => $tenant->id,
                'invoice_id' => $dto->invoiceId,
            ]);

            return [
                'created' => false,
                'payment' => null,
                'reason' => 'invoice_not_found',
            ];
        }

        // Processar pagamento em transação
        $payment = DB::transaction(function () use ($tenant, $dto, $invoice): BillingPayment {
            $payment = BillingPayment::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'invoice_id' => $dto->invoiceId,
                'amount' => $dto->amount,
                'payment_method' => $dto->paymentMethod,
                'provider' => $dto->provider,
                'provider_payment_id' => $dto->providerPaymentId,
                'status' => BillingPaymentStatus::CONFIRMED,
                'confirmed_at' => now(),
                'metadata' => [
                    'processed_at' => now()->toIso8601String(),
                    'source' => 'process_payment_action',
                ],
            ]);

            // Atualizar status da fatura se pagamento total
            $invoiceAmount = Money::of((string) $invoice->amount, 'BRL');
            $paidAmount = Money::of((string) $dto->amount, 'BRL');

            if (! $invoiceAmount->getAmount()->isGreaterThan($paidAmount->getAmount())) {
                $invoice->update([
                    'status' => BillingInvoiceStatus::PAID,
                    'paid_at' => now(),
                ]);
            }

            return $payment;
        });

        // Marcar como processado no cache
        $this->markAsProcessed($idempotencyKey, $payment->id);

        Log::info('[ProcessPaymentAction] Pagamento processado com sucesso', [
            'tenant_id' => $tenant->id,
            'payment_id' => $payment->id,
            'invoice_id' => $dto->invoiceId,
            'amount' => $dto->amount,
        ]);

        return [
            'created' => true,
            'payment' => $payment,
            'reason' => null,
        ];
    }

    /**
     * Gera chave de idempotência única para o pagamento.
     *
     * Baseada em: tenant_id + invoice_id + amount + date
     */
    private function generateIdempotencyKey(PlatformTenant $tenant, BillingPaymentDTO $dto): string
    {
        $date = now()->format('Y-m-d');
        $payload = sprintf(
            '%s:%s:%s:%s',
            $tenant->id,
            $dto->invoiceId,
            number_format($dto->amount, 2, '.', ''),
            $date
        );

        return sprintf('%s:%s', self::CACHE_PREFIX, md5($payload));
    }

    /**
     * Verifica se o pagamento já foi processado.
     */
    private function hasBeenProcessed(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * Obtém o ID do pagamento cacheado.
     */
    private function getCachedPaymentId(string $key): ?string
    {
        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($key);

        if (! is_array($cached) || ! isset($cached['payment_id'])) {
            return null;
        }

        return is_string($cached['payment_id']) ? $cached['payment_id'] : null;
    }

    /**
     * Marca o pagamento como processado no cache.
     */
    private function markAsProcessed(string $key, string $paymentId): void
    {
        Cache::put($key, [
            'payment_id' => $paymentId,
            'processed_at' => now()->toIso8601String(),
        ], self::IDEMPOTENCY_TTL);
    }

    /**
     * Limpa a chave de idempotência (para testes ou retry manual).
     */
    public function clearIdempotencyKey(PlatformTenant $tenant, BillingPaymentDTO $dto): void
    {
        $key = $this->generateIdempotencyKey($tenant, $dto);
        Cache::forget($key);

        Log::info('[ProcessPaymentAction] Chave de idempotência limpa', [
            'tenant_id' => $tenant->id,
            'invoice_id' => $dto->invoiceId,
        ]);
    }
}
