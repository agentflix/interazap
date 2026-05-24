<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Billing\Jobs;

use Domain\Billing\Jobs\ReconcileFailedUsageJob;
use Domain\Billing\Services\UsageCounterService;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReconcileFailedUsageJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function it_reconciles_unreconciled_logs(): void
    {
        $plan = PlatformPlan::factory()->create(['message_limit_monthly' => 100]);
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id, 'billing_cycle_anchor_day' => 1]);

        \Domain\Billing\Models\AiMessageUsageFailedLog::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'ai_turn_id' => 'turn-fail-1',
            'channel' => 'whatsapp',
            'attempted_at' => now()->subHour(),
            'reason' => 'api timeout',
        ]);

        $job = app(ReconcileFailedUsageJob::class);
        $job->handle(app(UsageCounterService::class));

        $log = \Domain\Billing\Models\AiMessageUsageFailedLog::query()->where('ai_turn_id', 'turn-fail-1')->first();
        $this->assertNotNull($log->reconciled_at);
    }
}
