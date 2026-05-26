<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource para serialização de campo personalizado do CRM.
 */
final class CRMCustomFieldResource extends BaseJsonResource
{
    /**
     * Transforma o recurso em array para resposta JSON.
     *
     * @return array<string, mixed> Dados serializados do campo personalizado.
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
