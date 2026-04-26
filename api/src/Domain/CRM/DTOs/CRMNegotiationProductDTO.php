<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for negotiation product item.
 *
 * @readonly
 */
final readonly class CRMNegotiationProductDTO
{
    public function __construct(
        public string $crm_negotiation_id,
        public string $name,
        public int $quantity,
        public float $unit_price,
        public ?string $crm_product_id = null,
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request, string $negotiationId): self
    {
        $data = $request->validated();

        return new self(
            crm_negotiation_id: $negotiationId,
            name: $data['name'],
            quantity: (int) $data['quantity'],
            unit_price: (float) $data['unit_price'],
            crm_product_id: $data['crm_product_id'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            crm_negotiation_id: $data['crm_negotiation_id'],
            name: $data['name'],
            quantity: (int) $data['quantity'],
            unit_price: (float) $data['unit_price'],
            crm_product_id: $data['crm_product_id'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'crm_negotiation_id' => $this->crm_negotiation_id,
            'crm_product_id' => $this->crm_product_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
        ];
    }
}
