<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConfigurationOpeningHourControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_opening_hours_crud(): void
    {
        $user = AuthUser::factory()->create();
        $this->actingAs($user, 'sanctum');

        $created = $this->postJson('/api/opening-hours', [
            'day_of_week' => 1,
            'open_time' => '09:00',
            'close_time' => '18:00',
            'is_active' => true,
        ])->assertCreated()->json('data');

        $hourId = $created['id'];

        $this->getJson('/api/opening-hours')
            ->assertOk()
            ->assertJsonFragment(['id' => $hourId]);

        $this->getJson('/api/opening-hours/'.$hourId)
            ->assertOk()
            ->assertJsonPath('data.id', $hourId);

        $this->putJson('/api/opening-hours/'.$hourId, [
            'day_of_week' => 1,
            'open_time' => '08:00',
            'close_time' => '17:00',
            'is_active' => false,
        ])->assertOk();

        $this->deleteJson('/api/opening-hours/'.$hourId)
            ->assertNoContent();
    }

    public function test_bulk_and_is_open_endpoint(): void
    {
        $user = AuthUser::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/opening-hours/bulk', [
            'opening_hours' => [
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
            ],
        ])->assertOk();

        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::create(2026, 1, 19, 10, 0, 0));
        $this->getJson('/api/opening-hours/is-open')
            ->assertOk()
            ->assertJsonPath('data.is_open', true);

        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::create(2026, 1, 19, 20, 0, 0));
        $this->getJson('/api/opening-hours/is-open')
            ->assertOk()
            ->assertJsonPath('data.is_open', false);

        \Illuminate\Support\Facades\Date::setTestNow();
    }
}
