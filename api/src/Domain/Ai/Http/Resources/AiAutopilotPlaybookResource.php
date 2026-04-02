<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Playbook serialization.
 */
final class AiAutopilotPlaybookResource extends BaseJsonResource
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
            'description' => $this->description,
            'trigger_type' => $this->trigger_type instanceof AutopilotTriggerType
                ? $this->trigger_type->value
                : $this->trigger_type,
            'version' => $this->version,
            'current_version' => $this->version,
            'steps' => $this->steps,
            'metadata' => $this->metadata,
            'is_active' => $this->is_active,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
