<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Actions;

use Domain\Ai\Actions\Prompts\UpdatePlanPromptAction;
use Domain\Ai\DTOs\PlanPromptDTO;
use Domain\Ai\Models\AiPromptPlan;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UpdatePlanPromptActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_execute_creates_plan_prompt_if_not_exists(): void
    {
        $plan = PlatformPlan::factory()->create();

        $dto = new PlanPromptDTO(
            content: 'System Prompt Content',
            tokenLimitMonthly: 1000,
            allowOverage: true
        );

        $action = new UpdatePlanPromptAction;
        $result = $action->execute($plan, $dto);

        $this->assertInstanceOf(AiPromptPlan::class, $result);
        $this->assertDatabaseHas('ai_prompt_plans', [
            'plan_id' => $plan->id,
            'content' => 'System Prompt Content',
            'token_limit_monthly' => 1000,
            'allow_overage' => true,
        ]);
    }

    public function test_execute_updates_existing_plan_prompt(): void
    {
        $plan = PlatformPlan::factory()->create();

        \Domain\Ai\Models\AiPromptPlan::query()->create([
            'plan_id' => $plan->id,
            'content' => 'Old Content',
            'token_limit_monthly' => 500,
        ]);

        $dto = new PlanPromptDTO(
            content: 'New Content',
            tokenLimitMonthly: 2000,
            allowOverage: false
        );

        $action = new UpdatePlanPromptAction;
        $result = $action->execute($plan, $dto);

        $this->assertEquals('New Content', $result->content);
        $this->assertEquals(2000, $result->token_limit_monthly);

        $this->assertDatabaseHas('ai_prompt_plans', [
            'plan_id' => $plan->id,
            'content' => 'New Content',
            'token_limit_monthly' => 2000,
            'allow_overage' => false,
        ]);

        $this->assertCount(1, \Domain\Ai\Models\AiPromptPlan::query()->where('plan_id', $plan->id)->get());
    }
}
