<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Usage Agent serialization.
 */
final class UsageAgentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'agent_name' => $this->agent_name,
            'total_requests' => (int) $this->total_requests,
            'total_tokens' => (int) $this->total_tokens,
            'total_cost' => round((float) $this->total_cost, 4),
            'formatted_cost' => '$'.number_format((float) $this->total_cost, 2),
            'avg_cost_per_request' => $this->total_requests > 0
                ? round((float) $this->total_cost / (int) $this->total_requests, 6)
                : 0,
        ];
    }
}
