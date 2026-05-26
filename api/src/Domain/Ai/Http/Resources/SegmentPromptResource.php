<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Ai\Models\AiPromptSegment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para serialização de segment prompt.
 *
 * @mixin AiPromptSegment
 */
final class SegmentPromptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'master_id' => $this->master_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'content' => $this->content,
            'is_active' => $this->is_active,
            'is_general' => $this->isGeneral(),
            'master' => $this->whenLoaded('master', fn () => new MasterPromptResource($this->master)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
