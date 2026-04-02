<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Http\Controllers;

use Domain\Ai\Services\TokenBudgetService;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @group ai
 * @group controllers
 */
class AiBudgetControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformTenant $tenant;

    private AuthUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = PlatformTenant::factory()->create();
        $this->user = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'ai.autopilots.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $this->user->givePermissionTo($permission);
    }

    public function test_show_returns_budget_config_and_usage(): void
    {
        Sanctum::actingAs($this->user);

        /** @var TokenBudgetService $service */
        $service = app(TokenBudgetService::class);
        $service->updateBudgetConfig((string) $this->tenant->id, 20.0, 200.0);
        $service->recordUsage((string) $this->tenant->id, 5.0);

        $response = $this->getJson('/api/ai/budget');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'config' => [
                        'daily_limit',
                        'monthly_limit',
                        'is_disabled',
                    ],
                    'usage' => [
                        'daily_usage',
                        'daily_limit',
                        'daily_ratio',
                        'monthly_usage',
                        'monthly_limit',
                        'monthly_ratio',
                    ],
                ],
            ]);

        $this->assertEquals(20, $response->json('data.config.daily_limit'));
        $this->assertEquals(5, $response->json('data.usage.daily_usage'));
    }

    public function test_update_changes_limits_and_resets_disabled_flag(): void
    {
        Sanctum::actingAs($this->user);

        /** @var TokenBudgetService $service */
        $service = app(TokenBudgetService::class);
        $service->updateBudgetConfig((string) $this->tenant->id, 10.0, null);
        $service->recordUsage((string) $this->tenant->id, 11.0);

        $blocked = $service->canExecuteRun((string) $this->tenant->id, 0.1);
        expect($blocked['allowed'])->toBeFalse();
        expect($blocked['reason'])->toBe('budget_auto_disabled');

        $response = $this->putJson('/api/ai/budget', [
            'daily_limit' => 100.0,
            'monthly_limit' => 1000.0,
        ]);

        $response->assertOk();
        $this->assertEquals(false, $response->json('data.config.is_disabled'));
        $this->assertEquals(100, $response->json('data.config.daily_limit'));
        $this->assertEquals(1000, $response->json('data.config.monthly_limit'));
    }

    public function test_budget_endpoints_require_authentication(): void
    {
        $this->getJson('/api/ai/budget')->assertUnauthorized();
        $this->putJson('/api/ai/budget', [
            'daily_limit' => 10,
        ])->assertUnauthorized();
    }
}
