<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource para serialização de item de negociação do CRM.
 */
final class CRMNegotiationProductResource extends BaseJsonResource
{
    /**
     * Transforma o recurso em array para resposta JSON.
     *
     * @return array<string, mixed> Dados serializados do item de negociação.
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'crm_negotiation_id' => $this->crm_negotiation_id,
            'crm_product_id' => $this->crm_product_id,
            'name' => $this->name,
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total' => (float) $this->total,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
