<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Negotiation Task serialization.
 */
final class CRMNegotiationTaskResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'negotiation_id' => $this->crm_negotiation_id,
            'crm_negotiation_id' => $this->crm_negotiation_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'due_date' => $this->iso($this->due_date),
            'assigned_to' => $this->auth_user_id,
            'user_id' => $this->auth_user_id,
            'negotiation' => $this->when(
                $this->resource->relationLoaded('negotiation'),
                fn () => [
                    'id' => $this->negotiation?->id,
                    'title' => $this->negotiation?->title,
                    'crm_company' => $this->when(
                        $this->negotiation?->relationLoaded('company'),
                        fn () => [
                            'id' => $this->negotiation?->company?->id,
                            'name' => $this->negotiation?->company?->name,
                        ]
                    ),
                ]
            ),
            'user' => $this->when(
                $this->resource->relationLoaded('assignee'),
                fn () => [
                    'id' => $this->assignee?->id,
                    'name' => $this->assignee?->name,
                ]
            ),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
