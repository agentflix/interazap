<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Billing;

use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckAndIncrementEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.gateway.api_key', 'test-s2s-key');
    }

    #[Test]
    public function it_returns_200_with_valid_payload(): void
    {
        $plan = PlatformPlan::factory()->create(['message_limit_monthly' => 100]);
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id, 'billing_cycle_anchor_day' => 1]);

        $response = $this->withHeader('X-Internal-Api-Key', 'test-s2s-key')
            ->postJson('/api/billing/usage/check-and-increment', [
                'tenant_id' => $tenant->id,
                'channel' => 'whatsapp',
                'ai_turn_id' => '550e8400-e29b-41d4-a716-446655440001',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['allowed', 'current', 'limit', 'mode', 'is_overage']]);
    }

    #[Test]
    public function it_returns_422_without_ai_turn_id(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $response = $this->withHeader('X-Internal-Api-Key', 'test-s2s-key')
            ->postJson('/api/billing/usage/check-and-increment', [
                'tenant_id' => $tenant->id,
                'channel' => 'whatsapp',
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_returns_401_without_auth(): void
    {
        $response = $this->postJson('/api/billing/usage/check-and-increment', [
            'tenant_id' => 'some-id',
            'channel' => 'whatsapp',
            'ai_turn_id' => 'turn-1',
        ]);
        $response->assertStatus(401);
    }
}
