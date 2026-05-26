<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para serialização de notificação de vendedor da IA.
 */
final class AiNotificationResource extends JsonResource
{
    /**
     * Transforma o recurso em array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'message' => $this->resource->message,
            'reason' => $this->resource->reason->value,
            'reason_label' => $this->resource->reason->label(),
            'channel' => $this->resource->channel->value,
            'priority' => $this->resource->priority,
            'is_read' => $this->resource->delivered_at !== null,
            'read_at' => $this->resource->delivered_at?->toIso8601String(),
            'scheduled_at' => $this->resource->scheduled_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'notifiable_type' => $this->resource->notifiable_type,
            'notifiable_id' => $this->resource->notifiable_id,
            'metadata' => $this->resource->metadata,
        ];
    }
}
