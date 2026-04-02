<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Proposal serialization.
 */
final class CRMProposalResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'crm_negotiation_id' => $this->crm_negotiation_id,
            'title' => $this->title,
            'number' => $this->number,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'valid_until' => $this->iso($this->valid_until),
            'total' => (float) $this->total,
            'public_token' => $this->public_token,
            'notes' => $this->notes,
            'sent_at' => $this->iso($this->sent_at),
            'viewed_at' => $this->iso($this->viewed_at),
            'accepted_at' => $this->iso($this->accepted_at),
            'rejected_at' => $this->iso($this->rejected_at),
            'items' => CRMProposalItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
