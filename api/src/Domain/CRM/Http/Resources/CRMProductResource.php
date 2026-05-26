<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource para serialização de produto ou serviço do CRM.
 */
final class CRMProductResource extends BaseJsonResource
{
    /**
     * Transforma o recurso em array para resposta JSON.
     *
     * @return array<string, mixed> Dados serializados do produto/serviço.
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'type' => $this->type,
            'price' => (float) $this->price,
            'cost' => $this->cost !== null ? (float) $this->cost : null,
            'unit' => $this->unit,
            'stock_quantity' => (int) ($this->stock_quantity ?? $this->stock),
            'min_stock' => (int) ($this->min_stock ?? 0),
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) ($this->is_featured ?? false),
            'track_stock' => (bool) $this->track_stock,
            'stock' => (int) $this->stock,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
