<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Http\Controllers;

use Domain\Ai\Contracts\AIServiceInterface;
use Domain\Ai\Models\AiAutopilotPlaybook;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Gateway\DTOs\AI\AICompletionRequest;
use Domain\Gateway\DTOs\AI\AICompletionResponse;
use Domain\Gateway\Enums\AIFinishReason;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @group ai
 * @group controllers
 */
class AiAutopilotRunControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformTenant $tenant;

    private AuthUser $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformPlanSeeder::class);
        $this->tenant = PlatformTenant::factory()->create();
        $this->user = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'ai.autopilots.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $this->user->givePermissionTo($permission);

        $this->app->instance(AIServiceInterface::class, new class implements AIServiceInterface
        {
            public function complete(AICompletionRequest $request): AICompletionResponse
            {
                return new AICompletionResponse(
                    content: 'ok',
                    promptTokens: 10,
                    completionTokens: 5,
                    totalTokens: 15,
                    model: 'gpt-4o',
                    finishReason: AIFinishReason::STOP,
                    toolCalls: [],
                );
            }

            public function getProvider(): string
            {
                return 'openai';
            }
        });
    }

    public function test_playbook_run_endpoint_returns_202_accepted_with_location_header(): void
    {
        Sanctum::actingAs($this->user);

        $playbook = AiAutopilotPlaybook::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson(
            "/api/ai/playbooks/{$playbook->id}/runs",
            ['context' => ['agent_role' => 'general']]
        );

        $response->assertStatus(202)
            ->assertHeader('Location')
            ->assertJsonPath('data.playbook_id', $playbook->id)
            ->assertJsonPath('data.status', 'queued');
    }

    public function test_store_run_endpoint_returns_202_accepted_with_location_header(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai/runs', [
            'playbook_id' => null,
            'context' => ['agent_role' => 'general'],
        ]);

        $response->assertStatus(202)
            ->assertHeader('Location')
            ->assertJsonPath('data.status', 'queued');
    }
}
