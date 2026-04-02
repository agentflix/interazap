<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Domain\Configuration\Actions\ConfigurationOpeningHourActions;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ConfigurationOpeningHourActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_crud_and_list_opening_hours(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        $actions = new ConfigurationOpeningHourActions;

        $created = $actions->create($tenant->id, [
            'day_of_week' => 1,
            'open_time' => '09:00',
            'close_time' => '18:00',
            'is_active' => true,
        ]);

        $actions->create($otherTenant->id, [
            'day_of_week' => 2,
            'open_time' => '10:00',
            'close_time' => '16:00',
            'is_active' => true,
        ]);

        $list = $actions->list($tenant->id);
        $this->assertCount(1, $list);

        $updated = $actions->update($tenant->id, $created->id, [
            'open_time' => '08:00',
            'close_time' => '17:00',
            'is_active' => false,
        ]);

        $this->assertSame('08:00', $updated->open_time);
        $this->assertFalse($updated->is_active);

        $actions->delete($tenant->id, $created->id);
        $this->assertDatabaseMissing('configuration_opening_hours', [
            'id' => $created->id,
        ]);
    }

    public function test_bulk_replace_and_is_open(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $actions = new ConfigurationOpeningHourActions;

        $items = $actions->bulkReplace($tenant->id, [
            [
                'day_of_week' => 1,
                'open_time' => '09:00',
                'close_time' => '18:00',
                'is_active' => true,
            ],
            [
                'day_of_week' => 2,
                'open_time' => '10:00',
                'close_time' => '16:00',
                'is_active' => true,
            ],
        ]);

        $this->assertCount(2, $items);

        Date::setTestNow(Date::create(2026, 1, 19, 10, 0, 0));
        $open = $actions->isOpen($tenant->id);
        $this->assertTrue($open['is_open']);
        $this->assertNotNull($open['opening_hour']);

        Date::setTestNow(Date::create(2026, 1, 19, 20, 0, 0));
        $closed = $actions->isOpen($tenant->id);
        $this->assertFalse($closed['is_open']);

        Date::setTestNow();
    }
}
