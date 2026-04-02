<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Models\ConfigurationNotification;
use Domain\Configuration\Models\ConfigurationNotificationPreference;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ConfigurationNotificationModelsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_notification_status_helpers(): void
    {
        $user = AuthUser::factory()->create();

        $notification = ConfigurationNotification::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Aviso',
            'body' => 'Mensagem',
            'channel' => 'ui',
            'status' => ConfigurationNotification::STATUS_PENDING,
        ]);

        $notification->markAsSent();
        $this->assertSame(ConfigurationNotification::STATUS_SENT, $notification->fresh()->status);
        $this->assertNotNull($notification->fresh()->sent_at);

        $notification->markAsRead();
        $this->assertSame(ConfigurationNotification::STATUS_READ, $notification->fresh()->status);
        $this->assertNotNull($notification->fresh()->read_at);

        $notification->markAsFailed('error');
        $this->assertSame(ConfigurationNotification::STATUS_FAILED, $notification->fresh()->status);
        $this->assertSame('error', $notification->fresh()->error_message);

        $unreadCount = ConfigurationNotification::query()->unread()->count();
        $this->assertSame(1, $unreadCount);
    }

    public function test_notification_preference_helpers(): void
    {
        $user = AuthUser::factory()->create();

        $preference = ConfigurationNotificationPreference::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'notification_type' => 'new_ticket',
            'channels' => ['ui', 'email'],
            'enabled' => true,
            'quiet_start' => '22:00',
            'quiet_end' => '06:00',
        ]);

        $this->assertTrue($preference->hasChannel('email'));
        $this->assertFalse($preference->hasChannel('push'));
        $this->assertSame($user->id, $preference->user->id);
        $this->assertSame($user->tenant_id, $preference->tenant->id);

        Date::setTestNow(Date::create(2026, 1, 20, 23, 0, 0));
        $this->assertTrue($preference->isQuietHours());

        Date::setTestNow(Date::create(2026, 1, 20, 12, 0, 0));
        $this->assertFalse($preference->isQuietHours());

        $preference->update([
            'quiet_start' => '09:00',
            'quiet_end' => '18:00',
        ]);

        Date::setTestNow(Date::create(2026, 1, 20, 10, 0, 0));
        $this->assertTrue($preference->fresh()->isQuietHours());

        Date::setTestNow(Date::create(2026, 1, 20, 20, 0, 0));
        $this->assertFalse($preference->fresh()->isQuietHours());

        Date::setTestNow();
    }
}
