<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Platform\Enums\PlatformReportsMode;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlatformReportsModeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_enum_has_three_cases(): void
    {
        $cases = PlatformReportsMode::cases();

        $this->assertCount(3, $cases);
    }

    public function test_enum_values_are_backed_strings(): void
    {
        $this->assertSame('BASIC', PlatformReportsMode::BASIC->value);
        $this->assertSame('ADVANCED', PlatformReportsMode::ADVANCED->value);
        $this->assertSame('FULL', PlatformReportsMode::FULL->value);
    }

    public function test_plan_casts_reports_mode_to_enum(): void
    {
        $plan = PlatformPlan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'limit_users' => 10,
            'storage_mode' => 'LIMITED',
            'storage_limit_bytes' => 1024,
            'ai_enabled' => true,
            'whatsapp_integrations_limit' => 2,
            'negotiations_mode' => 'LIMITED',
            'negotiations_limit' => 5,
            'price_monthly' => 9.9,
            'reports_mode' => 'ADVANCED',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(PlatformReportsMode::class, $plan->reports_mode);
        $this->assertSame(PlatformReportsMode::ADVANCED, $plan->reports_mode);
    }

    public function test_plan_persists_each_reports_mode_value(): void
    {
        foreach (PlatformReportsMode::cases() as $case) {
            $plan = PlatformPlan::factory()->create(['reports_mode' => $case]);
            $plan->refresh();

            $this->assertSame($case, $plan->reports_mode, "Failed for {$case->name}");
        }
    }

    public function test_plan_persists_reports_mode_as_string_in_database(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::FULL]);

        $raw = $plan->getRawOriginal('reports_mode');

        $this->assertIsString($raw);
        $this->assertSame('FULL', $raw);
    }
}
