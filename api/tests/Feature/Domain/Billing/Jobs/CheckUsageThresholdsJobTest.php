<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Billing\Jobs;

use Domain\Billing\Jobs\CheckUsageThresholdsJob;
use Domain\Billing\Jobs\SendUsageAlertJob;
use Domain\Billing\Services\ThresholdChecker;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckUsageThresholdsJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function it_dispatches_alert_when_at_80_percent(): void
    {
        Queue::fake();

        $plan = PlatformPlan::factory()->create(['message_limit_monthly' => 10]);
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);

        $usage = \Domain\Billing\Models\TenantMessageUsage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'cycle_start' => now()->startOfMonth()->toDateString(),
            'cycle_end' => now()->endOfMonth()->toDateString(),
            'message_count' => 8, // 80%
            'overage_count' => 0,
        ]);

        (new CheckUsageThresholdsJob($tenant->id, $usage->cycle_start->toDateString()))->handle(
            app(ThresholdChecker::class)
        );

        Queue::assertPushed(SendUsageAlertJob::class);
    }
}
