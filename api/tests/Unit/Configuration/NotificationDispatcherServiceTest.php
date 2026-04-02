<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Jobs\SendNotificationJob;
use Domain\Configuration\Models\ConfigurationNotification;
use Domain\Configuration\Models\ConfigurationNotificationPreference;
use Domain\Configuration\Services\NotificationDispatcherService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class NotificationDispatcherServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dispatch_respects_preferences_and_queues_jobs(): void
    {
        Queue::fake();

        $user = AuthUser::factory()->create();

        ConfigurationNotificationPreference::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'notification_type' => 'new_ticket',
            'channels' => ['ui', 'email'],
            'enabled' => true,
        ]);

        $service = $this->app->make(NotificationDispatcherService::class);

        $service->dispatch(
            tenantId: (string) $user->tenant_id,
            userIds: [(string) $user->id],
            type: 'new_ticket',
            title: 'Novo ticket',
            body: 'Mensagem teste',
            data: ['ticket_id' => 't-1'],
            priority: 'high',
        );

        $this->assertDatabaseCount('configuration_notifications', 2);
        Queue::assertPushed(SendNotificationJob::class, 2);
    }

    public function test_dispatch_keeps_pending_during_quiet_hours(): void
    {
        Queue::fake();

        $user = AuthUser::factory()->create();
        $start = \Illuminate\Support\Facades\Date::now()->subHour()->format('H:i');
        $end = \Illuminate\Support\Facades\Date::now()->addHour()->format('H:i');

        ConfigurationNotificationPreference::query()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'notification_type' => 'billing',
            'channels' => ['ui'],
            'enabled' => true,
            'quiet_start' => $start,
            'quiet_end' => $end,
        ]);

        $service = $this->app->make(NotificationDispatcherService::class);

        $service->dispatch(
            tenantId: (string) $user->tenant_id,
            userIds: [(string) $user->id],
            type: 'billing',
            title: 'Cobrança',
            body: 'Em atraso',
            data: [],
            priority: 'urgent',
        );

        $this->assertDatabaseHas('configuration_notifications', [
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => 'billing',
            'status' => ConfigurationNotification::STATUS_PENDING,
        ]);

        Queue::assertNothingPushed();
    }
}
