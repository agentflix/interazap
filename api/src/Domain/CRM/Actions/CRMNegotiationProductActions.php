<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMNegotiationProductDTO;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationProduct;
use Domain\CRM\Models\CRMProduct;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Casos de uso para itens de negociação vinculados a produtos.
 * Regra: se já existir item para o mesmo produto na negociação, a quantidade é somada.
 */
final class CRMNegotiationProductActions
{
    public function list(string $tenantId, string $negotiationId): Collection
    {
        $this->ensureNegotiation($tenantId, $negotiationId);

        return CRMNegotiationProduct::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_id', $negotiationId)
            ->with('product')
            ->orderByDesc('created_at')
            ->get();
    }

    public function add(string $tenantId, CRMNegotiationProductDTO $dto): CRMNegotiationProduct
    {
        return DB::transaction(function () use ($tenantId, $dto): CRMNegotiationProduct {
            $this->ensureNegotiation($tenantId, $dto->crm_negotiation_id);
            $product = $this->resolveProduct($tenantId, $dto->crm_product_id);

            $existing = $dto->crm_product_id !== null
                ? $this->findByProduct($tenantId, $dto->crm_negotiation_id, $dto->crm_product_id)
                : null;

            $name = $dto->name;
            $unitPrice = $dto->unit_price;

            if ($product !== null) {
                $name = $dto->name ?: $product->name;
            }

            if ($existing !== null) {
                $this->applyStock($product, $dto->quantity);

                $existing->quantity += $dto->quantity;
                $existing->unit_price = $unitPrice;
                $existing->name = $name;
                $existing->total = $this->calculateTotal($existing->quantity, (float) $existing->unit_price);
                $existing->save();

                $this->recalculateNegotiationAmount($tenantId, $dto->crm_negotiation_id);

                return $existing->load('product');
            }

            $this->applyStock($product, $dto->quantity);

            $item = CRMNegotiationProduct::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                ...$dto->toArray(),
                'name' => $name,
                'total' => $this->calculateTotal($dto->quantity, $dto->unit_price),
            ]);

            $this->recalculateNegotiationAmount($tenantId, $dto->crm_negotiation_id);

            return $item->load('product');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $tenantId, string $id, array $data): CRMNegotiationProduct
    {
        return DB::transaction(function () use ($tenantId, $id, $data): CRMNegotiationProduct {
            $item = $this->find($tenantId, $id, withProduct: true);
            $negotiationId = (string) $item->crm_negotiation_id;

            $dto = CRMNegotiationProductDTO::fromArray([
                ...$data,
                'crm_negotiation_id' => $negotiationId,
            ]);

            $newProduct = $this->resolveProduct($tenantId, $dto->crm_product_id);
            /** @var CRMProduct|null $oldProduct */
            $oldProduct = $item->product instanceof CRMProduct ? $item->product : null;

            $this->applyStockDiff($oldProduct, $newProduct, $item->quantity, $dto->quantity);

            $name = $dto->name;
            if ($newProduct !== null) {
                $name = $dto->name ?: $newProduct->name;
            }

            $item->crm_product_id = $dto->crm_product_id;
            $item->name = $name;
            $item->quantity = $dto->quantity;
            $item->unit_price = $dto->unit_price;
            $item->total = $this->calculateTotal($dto->quantity, $dto->unit_price);
            $item->save();

            $this->recalculateNegotiationAmount($tenantId, $negotiationId);

            return $item->load('product');
        });
    }

    public function delete(string $tenantId, string $id): void
    {
        DB::transaction(function () use ($tenantId, $id): void {
            $item = $this->find($tenantId, $id, withProduct: true);
            $negotiationId = (string) $item->crm_negotiation_id;

            /** @var CRMProduct|null $relatedProduct */
            $relatedProduct = $item->product instanceof CRMProduct ? $item->product : null;
            if ($relatedProduct !== null) {
                $this->applyStock($relatedProduct, -$item->quantity);
            }

            $item->delete();

            $this->recalculateNegotiationAmount($tenantId, $negotiationId);
        });
    }

    private function find(string $tenantId, string $id, bool $withProduct = false): CRMNegotiationProduct
    {
        $query = CRMNegotiationProduct::query()
            ->where('tenant_id', $tenantId);

        if ($withProduct) {
            $query->with('product');
        }

        return $query->findOrFail($id);
    }

    private function findByProduct(string $tenantId, string $negotiationId, string $productId): ?CRMNegotiationProduct
    {
        return CRMNegotiationProduct::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_id', $negotiationId)
            ->where('crm_product_id', $productId)
            ->first();
    }

    private function resolveProduct(string $tenantId, ?string $productId): ?CRMProduct
    {
        if ($productId === null) {
            return null;
        }

        $product = CRMProduct::query()
            ->where('tenant_id', $tenantId)
            ->find($productId);

        if ($product === null) {
            throw ValidationException::withMessages([
                'crm_product_id' => ['Produto não encontrado para este tenant.'],
            ]);
        }

        return $product;
    }

    private function ensureNegotiation(string $tenantId, string $negotiationId): void
    {
        $exists = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $negotiationId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'crm_negotiation_id' => ['Negociação não encontrada para este tenant.'],
            ]);
        }
    }

    private function applyStockDiff(?CRMProduct $oldProduct, ?CRMProduct $newProduct, int $oldQty, int $newQty): void
    {
        if ($oldProduct !== null && $newProduct !== null && $oldProduct->id === $newProduct->id) {
            $diff = $newQty - $oldQty;
            $this->applyStock($newProduct, $diff);

            return;
        }

        if ($oldProduct !== null) {
            $this->applyStock($oldProduct, -$oldQty);
        }

        if ($newProduct !== null) {
            $this->applyStock($newProduct, $newQty);
        }
    }

    private function applyStock(?CRMProduct $product, int $diff): void
    {
        if ($product === null || ! $this->shouldTrackStock($product) || $diff === 0) {
            return;
        }

        // Lock the product row to prevent race conditions (overselling)
        $lockedProduct = CRMProduct::query()
            ->where('id', $product->id)
            ->lockForUpdate()
            ->first();

        if ($lockedProduct === null) {
            return;
        }

        if ($diff > 0) {
            if ($lockedProduct->stock < $diff) {
                throw ValidationException::withMessages([
                    'stock' => ['Estoque insuficiente para o produto selecionado.'],
                ]);
            }

            $lockedProduct->decrement('stock', $diff);

            return;
        }

        $lockedProduct->increment('stock', abs($diff));
    }

    private function shouldTrackStock(CRMProduct $product): bool
    {
        return (bool) $product->track_stock || $product->stock > 0;
    }

    private function calculateTotal(int $quantity, float $unitPrice): float
    {
        return round($quantity * $unitPrice, 2);
    }

    /**
     * Recalculates and persists the negotiation amount as the sum of all product totals.
     * This keeps `amount` as a computed cache derived from products.
     */
    private function recalculateNegotiationAmount(string $tenantId, string $negotiationId): void
    {
        $amount = (float) CRMNegotiationProduct::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_id', $negotiationId)
            ->sum('total');

        CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $negotiationId)
            ->update(['amount' => round($amount, 2)]);
    }
}
