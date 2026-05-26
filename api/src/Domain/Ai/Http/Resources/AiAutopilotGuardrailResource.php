<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource para serialização de guardrail do autopilot.
 */
final class AiAutopilotGuardrailResource extends BaseJsonResource
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
            'rule_type' => $this->rule_type,
            'conditions' => $this->conditions,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
