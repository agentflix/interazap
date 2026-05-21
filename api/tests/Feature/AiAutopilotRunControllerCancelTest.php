<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Models\AiAutopilotRun;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

final class AiAutopilotRunControllerCancelTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cancel_endpoint_marks_run_cancelled_and_publishes_cancel_request_event(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'ai.autopilots.run', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($permission);
        Sanctum::actingAs($user);

        $run = AiAutopilotRun::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'status' => 'running',
        ]);

        $redisMock = Mockery::mock();
        $redisMock->shouldReceive('publish')
            ->once()
            ->withArgs(function (string $channel, string $payload) use ($run, $tenant): bool {
                $decoded = json_decode($payload, true);

                return $channel === 'ai.run.cancel_requested'
                    && is_array($decoded)
                    && ($decoded['run_id'] ?? null) === (string) $run->id
                    && ($decoded['tenant_id'] ?? null) === (string) $tenant->id;
            });

        Redis::shouldReceive('connection')
            ->once()
            ->with(config('gateway.redis.connection', 'gateway'))
            ->andReturn($redisMock);

        $response = $this->patchJson("/api/ai/runs/{$run->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }
}
