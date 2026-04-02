<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Models\ConfigurationNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConfigurationNotificationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_notification_list_and_read_endpoints(): void
    {
        $user = AuthUser::factory()->create();
        $otherUser = AuthUser::factory()->create();

        $firstUnread = ConfigurationNotification::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Aviso 1',
            'body' => 'Mensagem',
            'channel' => 'ui',
            'status' => ConfigurationNotification::STATUS_PENDING,
        ]);

        ConfigurationNotification::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Aviso 2',
            'body' => 'Mensagem 2',
            'channel' => 'ui',
            'status' => ConfigurationNotification::STATUS_PENDING,
        ]);

        ConfigurationNotification::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Lida',
            'body' => 'Ok',
            'channel' => 'ui',
            'status' => ConfigurationNotification::STATUS_READ,
        ]);

        ConfigurationNotification::query()->create([
            'tenant_id' => $otherUser->tenant_id,
            'user_id' => $otherUser->id,
            'type' => 'system',
            'title' => 'Outro',
            'body' => 'Outro',
            'channel' => 'ui',
            'status' => ConfigurationNotification::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/notifications?limit=1')
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(1, 'data');

        $this->patchJson('/api/notifications/'.$firstUnread->id.'/read')
            ->assertOk();

        $this->assertDatabaseHas('configuration_notifications', [
            'id' => $firstUnread->id,
            'status' => ConfigurationNotification::STATUS_READ,
        ]);

        $this->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_notification_preferences_endpoints(): void
    {
        $user = AuthUser::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/notifications/preferences')
            ->assertOk()
            ->assertJsonStructure(['data', 'types', 'channels']);

        $this->putJson('/api/notifications/preferences/new_ticket', [
            'channels' => ['email'],
            'enabled' => false,
            'quiet_start' => '09:00',
            'quiet_end' => '18:00',
        ])->assertOk();

        $this->assertDatabaseHas('configuration_notification_preferences', [
            'user_id' => $user->id,
            'notification_type' => 'new_ticket',
            'enabled' => 0,
        ]);

        $this->putJson('/api/notifications/preferences', [
            'preferences' => [
                ['type' => 'new_ticket', 'channels' => ['ui'], 'enabled' => true],
                ['type' => 'invalid', 'channels' => ['ui'], 'enabled' => true],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
