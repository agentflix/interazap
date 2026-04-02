<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Models\ConfigurationNotificationPreference;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfigurationNotificationPreferencesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): AuthUser
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return $user;
    }

    public function test_update_preference_and_isolation_by_user_tenant(): void
    {
        $user = $this->acting();

        $this->putJson('/api/notifications/preferences/new_ticket', [
            'channels' => ['ui', 'email'],
            'enabled' => true,
            'quiet_start' => '22:00',
            'quiet_end' => '07:00',
        ])->assertOk()->assertJsonFragment(['notification_type' => 'new_ticket']);

        $pref = ConfigurationNotificationPreference::query()->first();
        $this->assertNotNull($pref);
        $this->assertEquals($user->tenant_id, $pref->tenant_id);
        $this->assertEquals($user->id, $pref->user_id);
        $this->assertTrue($pref->hasChannel('email'));

        // outro usuário/tenant não vê preferências do usuário atual
        $other = AuthUser::factory()->create();
        Sanctum::actingAs($other, abilities: ['*']);
        $this->getJson('/api/notifications/preferences')
            ->assertOk()
            ->assertJsonMissing(['user_id' => $user->id]);
    }

    public function test_invalid_notification_type_returns_422(): void
    {
        $this->acting();

        $this->putJson('/api/notifications/preferences/unknown', [
            'channels' => ['ui'],
        ])->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->putJson('/api/notifications/preferences/new_ticket', [
            'channels' => ['ui'],
        ])->assertStatus(401);
    }
}
