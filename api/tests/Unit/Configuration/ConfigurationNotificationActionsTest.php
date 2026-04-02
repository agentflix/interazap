<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Actions\ConfigurationNotificationActions;
use Domain\Configuration\Models\ConfigurationNotification;
use Domain\Configuration\Models\ConfigurationNotificationPreference;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConfigurationNotificationActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_list_and_mark_notifications(): void
    {
        $user = AuthUser::factory()->create();
        $otherUser = AuthUser::factory()->create();

        $firstUnread = ConfigurationNotification::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Aviso',
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

        $actions = new ConfigurationNotificationActions;

        $list = $actions->listUnread($user, 1);
        $this->assertCount(1, $list);
        $this->assertSame(2, $actions->unreadCount($user));

        $this->assertTrue($actions->markAsRead($user, $firstUnread->id));
        $this->assertDatabaseHas('configuration_notifications', [
            'id' => $firstUnread->id,
            'status' => ConfigurationNotification::STATUS_READ,
        ]);

        $this->assertFalse($actions->markAsRead($user, (string) Str::orderedUuid()));

        $updated = $actions->markAllAsRead($user);
        $this->assertSame(1, $updated);
    }

    public function test_preferences_management(): void
    {
        $user = AuthUser::factory()->create();
        $actions = new ConfigurationNotificationActions;

        ConfigurationNotificationPreference::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'notification_type' => 'new_ticket',
            'channels' => ['ui'],
            'enabled' => true,
        ]);

        $preferences = $actions->getAllPreferences($user);
        $this->assertArrayHasKey('new_ticket', $preferences);
        $this->assertCount(count(ConfigurationNotificationPreference::TYPES), $preferences);

        $updated = $actions->updatePreference(
            user: $user,
            type: 'new_ticket',
            channels: ['email'],
            enabled: false,
            quietStart: '09:00',
            quietEnd: '18:00',
        );

        $this->assertFalse($updated->enabled);
        $this->assertSame(['email'], $updated->channels);

        $bulk = $actions->updateAllPreferences($user, [
            ['type' => 'new_ticket', 'channels' => ['ui'], 'enabled' => true],
            ['type' => 'invalid', 'channels' => ['ui'], 'enabled' => true],
        ]);

        $this->assertCount(1, $bulk);
    }
}
