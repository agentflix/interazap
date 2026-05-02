<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Chat Routing Queue serialization.
 */
final class ChatRoutingQueueResource extends BaseJsonResource
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
            'instance_id' => $this->instance_id,
            'name' => $this->name,
            'is_enabled' => $this->is_enabled,
            'strategy' => $this->strategy,
            'max_open_tickets_per_agent' => $this->max_open_tickets_per_agent,
            'agents' => $this->whenLoaded('agents', fn () => $this->agents->sortBy('position')->values()->map(fn ($agent) => [
                'id' => $agent->id,
                'user_id' => $agent->user_id,
                'position' => $agent->position,
                'last_assigned_at' => $agent->last_assigned_at ? $agent->last_assigned_at->format(DATE_ATOM) : null,
                'is_active' => $agent->is_active,
                'skills' => $agent->relationLoaded('skills')
                    ? $agent->skills->pluck('skill')->all()
                    : [],
            ])),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
