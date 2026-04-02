<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Autopilot Trigger Log serialization.
 */
final class AiAutopilotTriggerLogResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'trigger_type' => $this->trigger_type,
            'source_id' => $this->source_id,
            'source_type' => $this->source_type,
            'playbook_id' => $this->playbook_id,
            'run_id' => $this->run_id,
            'status' => $this->status,
            'context' => $this->context,
            'skip_reason' => $this->skip_reason,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
