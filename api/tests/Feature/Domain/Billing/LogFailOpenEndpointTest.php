<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Billing;

use Domain\Billing\Models\AiMessageUsageFailedLog;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogFailOpenEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.gateway.api_key', 'test-s2s-key');
    }

    #[Test]
    public function it_logs_fail_open_attempt(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $response = $this->withHeader('X-Internal-Api-Key', 'test-s2s-key')
            ->postJson('/api/billing/usage/fail-open-log', [
                'tenant_id' => $tenant->id,
                'ai_turn_id' => '550e8400-e29b-41d4-a716-446655440002',
                'channel' => 'whatsapp',
                'attempted_at' => now()->toIso8601String(),
                'reason' => 'timeout',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'tenant_id',
                    'ai_turn_id',
                    'channel',
                    'attempted_at',
                    'reason',
                ],
            ]);

        $this->assertDatabaseHas('ai_message_usage_failed_log', [
            'ai_turn_id' => '550e8400-e29b-41d4-a716-446655440002',
            'tenant_id' => $tenant->id,
            'channel' => 'whatsapp',
            'reason' => 'timeout',
        ]);
    }

    #[Test]
    public function it_is_idempotent_for_same_ai_turn_id(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $payload = [
            'tenant_id' => $tenant->id,
            'ai_turn_id' => '550e8400-e29b-41d4-a716-446655440003',
            'channel' => 'whatsapp',
            'attempted_at' => now()->toIso8601String(),
            'reason' => 'network_error',
        ];

        $this->withHeader('X-Internal-Api-Key', 'test-s2s-key')
            ->postJson('/api/billing/usage/fail-open-log', $payload)
            ->assertStatus(201);

        $this->withHeader('X-Internal-Api-Key', 'test-s2s-key')
            ->postJson('/api/billing/usage/fail-open-log', $payload)
            ->assertStatus(201);

        $count = AiMessageUsageFailedLog::query()
            ->where('ai_turn_id', '550e8400-e29b-41d4-a716-446655440003')
            ->count();

        $this->assertSame(1, $count);
    }

    #[Test]
    public function it_returns_422_without_required_fields(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $response = $this->withHeader('X-Internal-Api-Key', 'test-s2s-key')
            ->postJson('/api/billing/usage/fail-open-log', [
                'tenant_id' => $tenant->id,
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_returns_401_without_auth(): void
    {
        $response = $this->postJson('/api/billing/usage/fail-open-log', [
            'tenant_id' => 'some-id',
            'ai_turn_id' => 'turn-1',
            'channel' => 'whatsapp',
            'attempted_at' => now()->toIso8601String(),
        ]);

        $response->assertStatus(401);
    }
}
