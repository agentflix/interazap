<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Ai\Models\AiPromptPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Plan Prompt serialization.
 *
 * @mixin AiPromptPlan
 */
final class PlanPromptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'content' => $this->content,
            'mandatory_rules' => $this->mandatory_rules,
            'token_limit_monthly' => $this->token_limit_monthly,
            'allow_overage' => $this->allow_overage,
            'overage_price_per_1k' => $this->overage_price_per_1k,
            'is_active' => $this->is_active,
            'plan' => $this->whenLoaded('plan', fn () => [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'slug' => $this->plan->slug,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
