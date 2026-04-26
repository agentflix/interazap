<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

/**
 * DTO for proposal item.
 *
 * @readonly
 */
final readonly class CRMProposalItemDTO
{
    public function __construct(
        public string $name,
        public int $quantity,
        public float $unit_price,
        public float $discount = 0,
        public int $position = 0,
        public ?string $crm_product_id = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            quantity: (int) ($data['quantity'] ?? 1),
            unit_price: (float) ($data['unit_price'] ?? 0),
            discount: (float) ($data['discount'] ?? 0),
            position: (int) ($data['position'] ?? 0),
            crm_product_id: $data['crm_product_id'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromRequestItem(array $item): self
    {
        return self::fromArray($item);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $total = max(0, round(($this->quantity * $this->unit_price) - $this->discount, 2));

        return [
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'position' => $this->position,
            'total' => $total,
            'crm_product_id' => $this->crm_product_id,
        ];
    }
}
