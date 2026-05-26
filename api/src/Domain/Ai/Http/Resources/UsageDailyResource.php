<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para serialização de consumo diário de IA.
 */
final class UsageDailyResource extends JsonResource
{
    /**
     * Transforma o recurso em array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date,
            'requests' => (int) $this->requests,
            'tokens' => (int) $this->tokens,
            'cost' => round((float) $this->cost, 4),
            'formatted_cost' => '$'.number_format((float) $this->cost, 2),
        ];
    }
}
