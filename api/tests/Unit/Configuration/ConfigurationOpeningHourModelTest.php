<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Domain\Configuration\Models\ConfigurationOpeningHour;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConfigurationOpeningHourModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_scopes_filter_by_tenant_and_active(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        ConfigurationOpeningHour::query()->create([
            'tenant_id' => $tenant->id,
            'day_of_week' => 1,
            'open_time' => '09:00',
            'close_time' => '18:00',
            'is_active' => true,
        ]);

        ConfigurationOpeningHour::query()->create([
            'tenant_id' => $tenant->id,
            'day_of_week' => 2,
            'open_time' => '10:00',
            'close_time' => '16:00',
            'is_active' => false,
        ]);

        ConfigurationOpeningHour::query()->create([
            'tenant_id' => $otherTenant->id,
            'day_of_week' => 3,
            'open_time' => '08:00',
            'close_time' => '12:00',
            'is_active' => true,
        ]);

        $active = ConfigurationOpeningHour::query()
            ->forTenant($tenant->id)
            ->active()
            ->get();

        $this->assertCount(1, $active);
        $this->assertSame(1, $active->first()->day_of_week);
        $this->assertSame($tenant->id, $active->first()->tenant->id);
    }
}
