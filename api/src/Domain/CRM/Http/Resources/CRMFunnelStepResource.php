<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Funnel Step serialization.
 */
final class CRMFunnelStepResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'crm_negotiation_funnel_id' => $this->crm_negotiation_funnel_id,
            'name' => $this->name,
            'color' => $this->color,
            'is_active' => (bool) $this->is_active,
            'order' => (int) $this->order,
        ];
    }
}
