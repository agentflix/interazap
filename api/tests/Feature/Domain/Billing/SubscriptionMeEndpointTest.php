<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Billing;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionMeEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeInquilino(PlatformTenant $tenant): AuthUser
    {
        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);

        AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );
        $user->assignRole(AuthRole::INQUILINO_ID);
        $user->refresh();

        return $user;
    }

    #[Test]
    public function it_includes_ai_messages_in_usage(): void
    {
        $plan = PlatformPlan::factory()->create(['message_limit_monthly' => 800]);
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id, 'billing_cycle_anchor_day' => 1]);
        $user = $this->makeInquilino($tenant);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/billing/subscription');

        $response->assertStatus(200)
            ->assertJsonPath('data.usage.ai_messages.limit', 800)
            ->assertJsonPath('data.usage.ai_messages.current', 0)
            ->assertJsonStructure(['data' => ['usage' => ['ai_messages' => [
                'current', 'limit', 'percentage', 'overage_count',
                'mode', 'overage_price', 'cycle_start', 'cycle_end', 'cycle_label',
            ]]]]);
    }

    #[Test]
    public function it_returns_non_null_cycle_label(): void
    {
        $plan = PlatformPlan::factory()->create(['message_limit_monthly' => 100]);
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id, 'billing_cycle_anchor_day' => 15]);
        $user = $this->makeInquilino($tenant);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/billing/subscription');
        $cycleLabel = $response->json('data.usage.ai_messages.cycle_label');
        $this->assertNotNull($cycleLabel);
        $this->assertStringContainsString('/', $cycleLabel);
    }
}
