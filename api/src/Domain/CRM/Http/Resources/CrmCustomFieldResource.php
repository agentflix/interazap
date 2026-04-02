<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Custom Field serialization.
 */
final class CRMCustomFieldResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'type' => $this->type,
            'entity' => $this->entity,
            'options' => $this->options,
            'is_required' => (bool) $this->is_required,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
