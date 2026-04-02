<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Models\ConfigurationNotification;
use Domain\Configuration\Services\NotificationDispatcherService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationRateLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_dispatcher_debounces_repeated_entity_notifications_for_five_minutes(): void
    {
        Queue::fake();

        $user = AuthUser::factory()->create();
        $dispatcher = app(NotificationDispatcherService::class);

        $payload = [
            'entity_type' => 'ticket',
            'entity_id' => 'TICKET-1',
        ];

        $dispatcher->dispatch(
            tenantId: (string) $user->tenant_id,
            userIds: [(string) $user->id],
            type: 'new_ticket',
            title: 'Novo ticket',
            body: 'Primeiro evento',
            data: $payload,
            priority: 'normal',
        );

        $dispatcher->dispatch(
            tenantId: (string) $user->tenant_id,
            userIds: [(string) $user->id],
            type: 'new_ticket',
            title: 'Novo ticket',
            body: 'Evento duplicado',
            data: $payload,
            priority: 'normal',
        );

        $this->assertSame(
            1,
            ConfigurationNotification::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $user->id)
                ->count(),
        );
    }

    public function test_dispatcher_enters_digest_mode_after_five_notifications_per_minute(): void
    {
        Queue::fake();

        $user = AuthUser::factory()->create();
        $dispatcher = app(NotificationDispatcherService::class);

        for ($index = 1; $index <= 8; $index++) {
            $dispatcher->dispatch(
                tenantId: (string) $user->tenant_id,
                userIds: [(string) $user->id],
                type: 'system',
                title: 'Atualização de sistema',
                body: 'Evento '.$index,
                data: ['batch' => $index],
                priority: 'normal',
            );
        }

        $notifications = ConfigurationNotification::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)->oldest()
            ->get();

        $this->assertCount(6, $notifications);
        $this->assertTrue((bool) data_get($notifications->last()->data, 'digest_mode', false));
        $this->assertGreaterThanOrEqual(6, (int) data_get($notifications->last()->data, 'digest_count', 0));
    }
}
