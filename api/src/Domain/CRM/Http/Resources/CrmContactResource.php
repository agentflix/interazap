<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for CRM contact serialization.
 */
class CRMContactResource extends BaseJsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'crm_company_id' => $this->crm_company_id,
            'name' => $this->name,
            'email' => $this->email,
            'document' => $this->document,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'position' => $this->position,
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'custom_fields' => $this->custom_fields ?? [],
            'phones' => $this->whenLoaded('phones', fn () => $this->phones->map(fn ($p) => [
                'id' => $p->id,
                'label' => $p->label,
                'phone_e164' => $p->phone_e164,
                'is_primary' => (bool) $p->is_primary,
                'valid_from' => $this->iso($p->valid_from),
                'valid_to' => $this->iso($p->valid_to),
            ])),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
            'company' => $this->whenLoaded('company', fn () => new CRMCompanyResource($this->company)),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
