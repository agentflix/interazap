<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for CRM product/service.
 *
 * @readonly
 */
final readonly class CRMProductDTO
{
    public function __construct(
        public string $name,
        public float $price,
        public string $type = 'product',
        public ?string $code = null,
        public ?string $description = null,
        public ?float $cost = null,
        public ?string $unit = null,
        public int $stock_quantity = 0,
        public int $min_stock = 0,
        public bool $is_active = true,
        public bool $is_featured = false,
        public bool $track_stock = false,
        public int $stock = 0,
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = (string) ($data['type'] ?? 'product');
        $stockQuantity = (int) ($data['stock_quantity'] ?? $data['stock'] ?? 0);
        $trackStock = (bool) ($data['track_stock'] ?? ($type === 'product'));

        return new self(
            name: (string) ($data['name'] ?? ''),
            price: (float) ($data['price'] ?? 0),
            type: $type,
            code: isset($data['code']) ? trim((string) $data['code']) : null,
            description: $data['description'] ?? null,
            cost: array_key_exists('cost', $data) && $data['cost'] !== null ? (float) $data['cost'] : null,
            unit: isset($data['unit']) ? trim((string) $data['unit']) : null,
            stock_quantity: $type === 'product' ? $stockQuantity : 0,
            min_stock: $type === 'product' ? (int) ($data['min_stock'] ?? 0) : 0,
            is_active: (bool) ($data['is_active'] ?? true),
            is_featured: (bool) ($data['is_featured'] ?? false),
            track_stock: $type === 'product' ? $trackStock : false,
            stock: $type === 'product' ? $stockQuantity : 0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'type' => $this->type,
            'code' => $this->code,
            'description' => $this->description,
            'cost' => $this->cost,
            'unit' => $this->unit,
            'stock_quantity' => $this->stock_quantity,
            'min_stock' => $this->min_stock,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'track_stock' => $this->track_stock,
            'stock' => $this->stock,
        ];
    }
}
