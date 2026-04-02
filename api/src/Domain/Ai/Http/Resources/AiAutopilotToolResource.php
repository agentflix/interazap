<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Tool serialization.
 */
final class AiAutopilotToolResource extends BaseJsonResource
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
            'handler_class' => $this->handler_class,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'parameters_schema' => $this->parameters_schema,
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
