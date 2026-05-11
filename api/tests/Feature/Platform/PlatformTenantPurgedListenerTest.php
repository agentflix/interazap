<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Domain\Platform\Events\PlatformTenantPurgedEvent;
use Domain\Platform\Listeners\PlatformTenantPurgedListener;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Testa que o listener de auditoria para PlatformTenantPurgedEvent
 * está registrado no EventServiceProvider e escreve logs corretamente.
 */
final class PlatformTenantPurgedListenerTest extends TestCase
{
    public function test_platform_tenant_purged_event_has_listener_registered(): void
    {
        $this->assertTrue(
            $this->app->make(Dispatcher::class)->hasListeners(PlatformTenantPurgedEvent::class),
        );
    }

    public function test_platform_tenant_purged_listener_logs_event(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'platform.tenant_purged'
                    && isset($context['tenant_id'], $context['purged_at']);
            });

        app(PlatformTenantPurgedListener::class)->handle(
            new PlatformTenantPurgedEvent(
                tenantId: (string) Str::orderedUuid(),
                purgedAt: now()->toIso8601String(),
            )
        );
    }

    public function test_platform_tenant_purged_listener_logs_correct_tenant_id(): void
    {
        $tenantId = (string) Str::orderedUuid();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($tenantId): bool {
                return $message === 'platform.tenant_purged'
                    && $context['tenant_id'] === $tenantId;
            });

        app(PlatformTenantPurgedListener::class)->handle(
            new PlatformTenantPurgedEvent(
                tenantId: $tenantId,
                purgedAt: now()->toIso8601String(),
            )
        );
    }
}
