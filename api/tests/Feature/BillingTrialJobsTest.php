<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Domain\Billing\Jobs\CloseExpiredTrialJob;
use Domain\Billing\Jobs\SendTrialEndingSoonJob;
use Domain\Billing\Models\TenantMessageUsage;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingTrialJobsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformPlan $trialPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trialPlan = PlatformPlan::factory()->create([
            'slug' => 'trial',
            'is_trial' => true,
            'is_active' => true,
            'price_monthly' => 0.00,
            'cycle_days' => 7,
        ]);
    }

    private function createTrialTenant(): PlatformTenant
    {
        return PlatformTenant::factory()->create([
            'plan_id' => $this->trialPlan->id,
        ]);
    }

    private function createUsage(string $tenantId, string $cycleStart, string $cycleEnd, ?string $trialExpired = null): TenantMessageUsage
    {
        return TenantMessageUsage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'cycle_start' => $cycleStart,
            'cycle_end' => $cycleEnd,
            'message_count' => 0,
            'overage_count' => 0,
            'trial_expired_notified_at' => $trialExpired,
        ]);
    }

    // ===================== CloseExpiredTrialJob =====================

    public function test_close_expired_trial_marca_usage_expirado(): void
    {
        $tenant = $this->createTrialTenant();
        $yesterday = CarbonImmutable::yesterday()->toDateString();
        $twoDaysAgo = CarbonImmutable::today()->subDays(2)->toDateString();

        $usage = $this->createUsage($tenant->id, $twoDaysAgo, $yesterday);

        Log::spy();

        (new CloseExpiredTrialJob)->handle();

        $usage->refresh();
        $this->assertNotNull($usage->trial_expired_notified_at);
    }

    public function test_close_expired_trial_e_idempotente(): void
    {
        $tenant = $this->createTrialTenant();
        $yesterday = CarbonImmutable::yesterday()->toDateString();
        $twoDaysAgo = CarbonImmutable::today()->subDays(2)->toDateString();

        $sentAt = '2026-05-20 03:00:00';
        $usage = $this->createUsage($tenant->id, $twoDaysAgo, $yesterday, $sentAt);

        (new CloseExpiredTrialJob)->handle();

        $usage->refresh();
        // trial_expired_notified_at should NOT change — already marked
        $this->assertSame($sentAt, $usage->trial_expired_notified_at->toDateTimeString());
    }

    public function test_close_expired_trial_ignora_tenants_com_ciclo_ativo(): void
    {
        $tenant = $this->createTrialTenant();
        $today = CarbonImmutable::today()->toDateString();
        $nextWeek = CarbonImmutable::today()->addDays(7)->toDateString();

        $usage = $this->createUsage($tenant->id, $today, $nextWeek);

        (new CloseExpiredTrialJob)->handle();

        $usage->refresh();
        // Active cycle — should not be marked
        $this->assertNull($usage->trial_expired_notified_at);
    }

    public function test_close_expired_trial_ignora_tenants_sem_plano_trial(): void
    {
        // Tenant on a non-trial plan should be ignored
        $paidPlan = PlatformPlan::factory()->create([
            'is_trial' => false,
            'is_active' => true,
        ]);

        $tenant = PlatformTenant::factory()->create(['plan_id' => $paidPlan->id]);
        $yesterday = CarbonImmutable::yesterday()->toDateString();
        $twoDaysAgo = CarbonImmutable::today()->subDays(2)->toDateString();

        $usage = $this->createUsage($tenant->id, $twoDaysAgo, $yesterday);

        (new CloseExpiredTrialJob)->handle();

        $usage->refresh();
        $this->assertNull($usage->trial_expired_notified_at);
    }

    // ===================== SendTrialEndingSoonJob =====================

    public function test_ending_soon_marca_usage_com_ciclo_em_2_dias(): void
    {
        $tenant = $this->createTrialTenant();
        $today = CarbonImmutable::today()->toDateString();
        $inTwoDays = CarbonImmutable::today()->addDays(2)->toDateString();

        $usage = $this->createUsage($tenant->id, $today, $inTwoDays);

        Log::spy();

        (new SendTrialEndingSoonJob)->handle();

        $usage->refresh();
        $this->assertNotNull($usage->trial_ending_soon_notified_at);
    }

    public function test_ending_soon_e_idempotente(): void
    {
        $tenant = $this->createTrialTenant();
        $today = CarbonImmutable::today()->toDateString();
        $tomorrow = CarbonImmutable::tomorrow()->toDateString();

        $sentAt = '2026-05-24 09:00:00';
        $usage = $this->createUsage($tenant->id, $today, $tomorrow);
        $usage->forceFill(['trial_ending_soon_notified_at' => $sentAt])->save();

        (new SendTrialEndingSoonJob)->handle();

        $usage->refresh();
        $this->assertSame($sentAt, $usage->trial_ending_soon_notified_at->toDateTimeString());
    }

    public function test_ending_soon_ignora_ciclos_com_mais_de_2_dias(): void
    {
        $tenant = $this->createTrialTenant();
        $today = CarbonImmutable::today()->toDateString();
        $in5Days = CarbonImmutable::today()->addDays(5)->toDateString();

        $usage = $this->createUsage($tenant->id, $today, $in5Days);

        (new SendTrialEndingSoonJob)->handle();

        $usage->refresh();
        $this->assertNull($usage->trial_ending_soon_notified_at);
    }

    public function test_ending_soon_ignora_tenants_sem_plano_trial(): void
    {
        $paidPlan = PlatformPlan::factory()->create([
            'is_trial' => false,
            'is_active' => true,
        ]);

        $tenant = PlatformTenant::factory()->create(['plan_id' => $paidPlan->id]);
        $today = CarbonImmutable::today()->toDateString();
        $tomorrow = CarbonImmutable::tomorrow()->toDateString();

        $usage = $this->createUsage($tenant->id, $today, $tomorrow);

        (new SendTrialEndingSoonJob)->handle();

        $usage->refresh();
        $this->assertNull($usage->trial_ending_soon_notified_at);
    }
}
