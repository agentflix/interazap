<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Http\Controllers;

use Domain\Ai\Models\AiPromptPlan;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AiPromptPlanControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = AuthUser::factory()->create();

        if (! \Domain\Auth\Models\AuthRole::query()->where('id', \Domain\Auth\Models\AuthRole::INQUILINO_ID)->where('guard_name', 'sanctum')->exists()) {
            \Domain\Auth\Models\AuthRole::query()->firstOrCreate(['id' => \Domain\Auth\Models\AuthRole::INQUILINO_ID], ['name' => \Domain\Auth\Models\AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']);
        }
        $this->admin->assignRole(\Domain\Auth\Models\AuthRole::INQUILINO_ID);
    }

    public function test_index_returns_all_plan_prompts(): void
    {
        $plan = PlatformPlan::factory()->create();
        AiPromptPlan::factory()->create(['plan_id' => $plan->id]);

        $response = $this->actingAs($this->admin)->getJson(route('platform.ai.plans.index'));

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_show_returns_specific_plan_prompt(): void
    {
        $plan = PlatformPlan::factory()->create();
        $prompt = AiPromptPlan::factory()->create(['plan_id' => $plan->id]);

        $response = $this->actingAs($this->admin)->getJson(route('platform.ai.plans.show', $plan->id));

        $response->assertOk()
            ->assertJson(['data' => ['id' => $prompt->id]]);
    }

    public function test_show_returns_message_if_no_prompt_configured(): void
    {
        $plan = PlatformPlan::factory()->create();

        $response = $this->actingAs($this->admin)->getJson(route('platform.ai.plans.show', $plan->id));

        $response->assertOk()
            ->assertJson(['message' => 'No prompt configured for this plan.']);
    }

    public function test_update_creates_or_updates_plan_prompt(): void
    {
        $plan = PlatformPlan::factory()->create();

        $payload = [
            'content' => 'New System Prompt',
        ];

        $response = $this->actingAs($this->admin)->putJson(route('platform.ai.plans.update', $plan->id), $payload);

        $response->assertOk()
            ->assertJsonPath('data.content', 'New System Prompt');

        $this->assertDatabaseHas('ai_prompt_plans', [
            'plan_id' => $plan->id,
            'content' => 'New System Prompt',
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $plan = PlatformPlan::factory()->create();

        $response = $this->actingAs($this->admin)->putJson(route('platform.ai.plans.update', $plan->id), []);

        $response->assertUnprocessable();
    }
}
