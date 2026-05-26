<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Ai\Models\AiPromptTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para serialização de prompt customizado do tenant.
 *
 * @mixin AiPromptTenant
 */
final class TenantPromptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'segment_id' => $this->segment_id,
            'content' => $this->content,
            'previous_content' => $this->previous_content,
            'has_previous_content' => $this->hasPreviousContent(),
            'version' => $this->version,
            'validation_status' => $this->validation_status->value,
            'validation_status_label' => $this->validation_status->label(),
            'validation_status_color' => $this->validation_status->badgeColor(),
            'is_approved' => $this->isApproved(),
            'is_pending' => $this->isPending(),
            'is_quarantined' => $this->isQuarantined(),
            'is_rejected' => $this->isRejected(),
            'validated_at' => $this->validated_at?->toISOString(),
            'is_active' => $this->is_active,
            'segment' => $this->whenLoaded('segment', fn () => new SegmentPromptResource($this->segment)),
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
