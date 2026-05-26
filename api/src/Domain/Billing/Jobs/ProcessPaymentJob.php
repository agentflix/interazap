<?php

declare(strict_types=1);

namespace Domain\Billing\Jobs;

use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Services\BillingGatewayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Shared\Jobs\Middleware\RateLimitedJob;
use Shared\Jobs\Traits\Idempotent;
use Shared\Jobs\Traits\RetryableWithBackoff;

/**
 * Job para processamento de pagamentos com idempotência e retry com backoff.
 *
 * Garante que cobranças não sejam duplicadas usando transaction_id como chave
 * de idempotência no cache. Integra com o BillingGatewayService via Asaas.
 */
final class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable;
    use Idempotent;
    use InteractsWithQueue;
    use Queueable;
    use RetryableWithBackoff;
    use SerializesModels;

    /** TTL de idempotência: 7 dias em segundos. */
    protected int $idempotencyTtl = 604800;

    /** UUID do tenant dono do pagamento. */
    private readonly string $tenantId;

    /**
     * Retorna os intervalos de backoff (em segundos) para tentativas de reprocessamento.
     *
     * @return array<int, int>
     */
    protected function getBackoffDelays(): array
    {
        return [30, 120, 300, 600, 1800];
    }

    /**
     * Cria nova instância do job.
     *
     * @param  string  $transactionId  Identificador único da transação para idempotência
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $invoiceId  UUID da fatura a ser paga
     * @param  int  $amount  Valor em centavos
     * @param  string  $paymentMethod  Método de pagamento (pix, credit_card, boleto)
     * @param  array<string, mixed>  $paymentData  Dados adicionais (ex: dados de cartão)
     */
    public function __construct(
        private readonly string $transactionId,
        string $tenantId,
        private readonly string $invoiceId,
        private readonly int $amount,
        private readonly string $paymentMethod = 'pix',
        private readonly array $paymentData = [],
    ) {
        $this->tenantId = $tenantId;
        // Override trait defaults for payment-specific configuration
        $this->timeout = 120;
        $this->maxExceptions = 2;
    }

    /**
     * Middlewares que o job deve atravessar (ex: rate limiting de pagamentos).
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            RateLimitedJob::forPayment(),
        ];
    }

    /** Retorna o ID único do job baseado no transactionId (evita duplicatas na fila). */
    public function uniqueId(): string
    {
        return "payment:{$this->transactionId}";
    }

    /**
     * Retorna as tags do job para filtragem no Horizon.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'payment',
            "tenant:{$this->tenantId}",
            "invoice:{$this->invoiceId}",
            "method:{$this->paymentMethod}",
        ];
    }

    /**
     * Executa o job delegando ao trait Idempotent para garantir unicidade.
     */
    public function handle(BillingGatewayService $gateway): void
    {
        $this->executeIdempotent();
    }

    /**
     * Retorna o payload de idempotência usado pelo trait Idempotent como chave de cache.
     *
     * @return array<string, mixed>
     */
    protected function getIdempotencyPayload(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'tenant_id' => $this->tenantId,
            'invoice_id' => $this->invoiceId,
            'amount' => $this->amount,
            'payment_method' => $this->paymentMethod,
        ];
    }

    /**
     * Processa efetivamente o pagamento via gateway (chamado pelo trait Idempotent).
     *
     * @throws \InvalidArgumentException Quando fatura não existe ou valor diverge
     * @throws \RuntimeException Quando o gateway rejeita a cobrança
     */
    protected function process(): void
    {
        Log::info('[ProcessPaymentJob] Processing payment', [
            'transaction_id' => $this->transactionId,
            'tenant_id' => $this->tenantId,
            'invoice_id' => $this->invoiceId,
            'amount' => $this->amount,
            'payment_method' => $this->paymentMethod,
        ]);

        // Verify invoice exists and belongs to tenant
        $invoice = $this->getInvoice();
        if (! $invoice) {
            throw new \InvalidArgumentException("Invoice {$this->invoiceId} not found for tenant {$this->tenantId}");
        }

        // Check if already paid
        if ($invoice->isPaid()) {
            Log::info('[ProcessPaymentJob] Invoice already paid, skipping', [
                'transaction_id' => $this->transactionId,
                'invoice_id' => $this->invoiceId,
            ]);

            return;
        }

        // Validate amount matches (cast to int for comparison since model uses decimal:2)
        $invoiceAmountCents = (int) round($invoice->amount * 100);
        if ($invoiceAmountCents !== $this->amount) {
            throw new \InvalidArgumentException(
                "Amount mismatch: expected {$invoiceAmountCents} cents, got {$this->amount} cents"
            );
        }

        // Get customer ID from tenant
        $tenant = $invoice->tenant;
        $customerId = $tenant->asaas_customer_id ?? null;
        if (empty($customerId)) {
            throw new \InvalidArgumentException('Tenant does not have a payment customer ID configured');
        }

        // Process via gateway
        $gateway = app(BillingGatewayService::class);

        // Map payment method to billing type
        $billingType = match ($this->paymentMethod) {
            'credit_card' => 'CREDIT_CARD',
            'boleto' => 'BOLETO',
            default => 'PIX',
        };

        try {
            $result = $gateway->createPayment(
                customerId: $customerId,
                value: $invoice->amount,
                dueDate: $invoice->due_date->format('Y-m-d'),
                description: "Invoice {$this->invoiceId}",
                externalReference: $this->transactionId,
                billingType: $billingType,
                card: $this->paymentData['card'] ?? null,
                holderInfo: $this->paymentData['holder_info'] ?? null,
            );

            if (empty($result['id'])) {
                $gatewayReason = $gateway->getLastError();
                $details = $gatewayReason ? " ({$gatewayReason})" : '';

                throw new \RuntimeException('Failed to create payment: no ID returned'.$details);
            }

            $this->updateInvoiceStatus($invoice, 'processing', [
                'gateway_transaction_id' => $result['id'],
                'invoice_url' => $result['invoiceUrl'] ?? null,
                'processed_at' => now()->toIso8601String(),
            ]);

            Log::info('[ProcessPaymentJob] Payment processed successfully', [
                'transaction_id' => $this->transactionId,
                'gateway_transaction_id' => $result['id'],
            ]);
        } catch (\Throwable $e) {
            if ($this->shouldFailImmediately($e)) {
                Log::error('[ProcessPaymentJob] Non-retryable payment error', [
                    'transaction_id' => $this->transactionId,
                    'error' => $e->getMessage(),
                ]);

                $this->updateInvoiceStatus($invoice, 'failed', [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toIso8601String(),
                ]);

                throw $e;
            }

            throw $e;
        }
    }

    /**
     * Trata falha definitiva do job, registrando erro e atualizando status da fatura.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[ProcessPaymentJob] Job failed permanently', [
            'transaction_id' => $this->transactionId,
            'tenant_id' => $this->tenantId,
            'invoice_id' => $this->invoiceId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $invoice = $this->getInvoice();
        if ($invoice) {
            $this->updateInvoiceStatus($invoice, 'failed', [
                'error' => $exception->getMessage(),
                'failed_at' => now()->toIso8601String(),
                'attempts' => $this->attempts(),
            ]);
        }
    }

    /**
     * Determina se a exceção deve causar falha imediata sem tentativas de retry.
     *
     * Cartão inválido, sem saldo e fraude são erros não recuperáveis.
     */
    protected function shouldFailImmediately(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        // Invalid card/payment method
        if (str_contains($message, 'invalid card') || str_contains($message, 'declined')) {
            return true;
        }

        // Insufficient funds
        if (str_contains($message, 'insufficient funds')) {
            return true;
        }

        // Fraud detection
        if (str_contains($message, 'fraud') || str_contains($message, 'suspicious')) {
            return true;
        }

        // Invalid arguments
        if ($exception instanceof \InvalidArgumentException) {
            return true;
        }

        return false;
    }

    /** Busca a fatura no banco de dados garantindo pertencimento ao tenant. */
    private function getInvoice(): ?BillingInvoice
    {
        return BillingInvoice::query()
            ->where('tenant_id', $this->tenantId)
            ->find($this->invoiceId);
    }

    /**
     * Atualiza o status de pagamento da fatura nos metadados.
     *
     * @param  array<string, mixed>  $paymentData  Dados adicionais a mesclar nos metadados
     */
    private function updateInvoiceStatus(BillingInvoice $invoice, string $status, array $paymentData = []): void
    {
        $currentMetadata = $invoice->metadata ?? [];
        $paymentInfo = $currentMetadata['payment'] ?? [];

        $invoice->update([
            'metadata' => array_merge($currentMetadata, [
                'payment' => array_merge($paymentInfo, $paymentData, [
                    'transaction_id' => $this->transactionId,
                    'status' => $status,
                ]),
            ]),
        ]);
    }
}
