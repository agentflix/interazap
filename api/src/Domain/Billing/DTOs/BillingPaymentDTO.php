<?php

declare(strict_types=1);

namespace Domain\Billing\DTOs;

/**
 * DTO para transferência de dados de pagamento.
 *
 * @readonly
 */
final readonly class BillingPaymentDTO
{
    public function __construct(
        public string $invoiceId,
        public float $amount,
        public string $paymentMethod,
        public string $provider,
        public string $providerPaymentId,
        public string $status = 'confirmed',
    ) {}

    /**
     * Cria o DTO a partir de um array de dados.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            invoiceId: $data['invoice_id'],
            amount: (float) $data['amount'],
            paymentMethod: $data['payment_method'],
            provider: $data['provider'] ?? 'asaas',
            providerPaymentId: $data['provider_payment_id'],
            status: $data['status'] ?? 'confirmed',
        );
    }

    /**
     * Converte DTO para array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'amount' => $this->amount,
            'payment_method' => $this->paymentMethod,
            'provider' => $this->provider,
            'provider_payment_id' => $this->providerPaymentId,
            'status' => $this->status,
        ];
    }
}
