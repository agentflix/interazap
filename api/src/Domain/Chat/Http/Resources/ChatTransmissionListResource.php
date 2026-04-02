<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Chat Transmission List serialization.
 */
final class ChatTransmissionListResource extends BaseJsonResource
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
            'name' => $this->name,
            'status' => $this->status,
            'message' => $this->message,
            'filter_criteria' => $this->filter_criteria,
            'instance_id' => $this->instance_id,
            'scheduled_at' => $this->iso($this->scheduled_at),
            'metadata' => $this->metadata,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
