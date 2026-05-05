<?php

declare(strict_types=1);

namespace Domain\Billing\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for invoice data transfer.
 *
 * @readonly
 */
final readonly class BillingInvoiceDTO
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public ?string $planId,
        public string $referenceMonth,
        public float $amount,
        public string $status,
        public string $dueDate,
        public ?string $paymentMethod = null,
        public ?array $metadata = null,
    ) {}

    /**
     * Create DTO from validated form request.
     */
    public static function fromRequest(\Illuminate\Foundation\Http\FormRequest $request): self
    {
        $data = $request->validated();

        return new self(
            planId: $data['plan_id'] ?? null,
            referenceMonth: $data['reference_month'],
            amount: (float) $data['amount'],
            status: $data['status'] ?? 'draft',
            dueDate: $data['due_date'],
            paymentMethod: $data['payment_method'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * Cria DTO a partir de um array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            planId: $data['plan_id'] ?? null,
            referenceMonth: $data['reference_month'],
            amount: (float) $data['amount'],
            status: $data['status'] ?? 'draft',
            dueDate: $data['due_date'],
            paymentMethod: $data['payment_method'] ?? null,
            metadata: $data['metadata'] ?? null,
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
            'plan_id' => $this->planId,
            'reference_month' => $this->referenceMonth,
            'amount' => $this->amount,
            'status' => $this->status,
            'due_date' => $this->dueDate,
            'payment_method' => $this->paymentMethod,
            'metadata' => $this->metadata,
        ];
    }
}
