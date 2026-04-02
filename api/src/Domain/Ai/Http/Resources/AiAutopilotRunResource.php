<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Autopilot Run serialization.
 */
final class AiAutopilotRunResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'playbook_id' => $this->playbook_id,
            'status' => $this->status,
            'playbook_version' => $this->playbook_version,
            'input_context' => $this->input_context,
            'output' => $this->output,
            'error_message' => data_get($this->output, 'error'),
            'actions' => (array) data_get($this->output, 'tool_trace', []),
            'started_at' => $this->iso($this->started_at),
            'completed_at' => $this->iso($this->completed_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
