<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Proposal Item serialization.
 */
final class CRMProposalItemResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'crm_proposal_id' => $this->crm_proposal_id,
            'crm_product_id' => $this->crm_product_id,
            'name' => $this->name,
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'discount' => (float) ($this->discount ?? 0),
            'position' => (int) ($this->position ?? 0),
            'total' => (float) $this->total,
        ];
    }
}
