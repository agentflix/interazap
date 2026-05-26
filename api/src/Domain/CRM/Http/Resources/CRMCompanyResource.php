<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource para serialização de empresa do CRM.
 */
class CRMCompanyResource extends BaseJsonResource
{
    /**
     * Transforma o recurso em array para resposta JSON.
     *
     * @return array<string, mixed> Dados serializados da empresa.
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'document' => $this->document,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'is_active' => (bool) $this->is_active,
            'custom_fields' => $this->whenLoaded('customFieldValues', fn () => $this->customFieldValues->map(fn ($value) => [
                'crm_custom_field_id' => $value->crm_custom_field_id,
                'value' => $value->value,
                'field' => [
                    'name' => $value->field?->name,
                    'type' => $value->field?->type,
                    'entity' => $value->field?->entity,
                    'options' => $value->field?->options,
                    'is_required' => $value->field?->is_required,
                ],
            ])),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
