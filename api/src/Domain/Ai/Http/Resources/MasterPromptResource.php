<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Ai\Models\AiPromptMaster;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Master Prompt serialization.
 *
 * @mixin AiPromptMaster
 */
final class MasterPromptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'content' => $this->content,
            'version' => $this->version,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
