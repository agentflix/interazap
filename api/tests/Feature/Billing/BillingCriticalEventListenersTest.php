<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Domain\Billing\Events\BillingCollectionSentEvent;
use Domain\Billing\Events\BillingPurgeWarningEvent;
use Domain\Billing\Events\BillingTenantGraceEvent;
use Domain\Billing\Events\BillingTenantLockedEvent;
use Domain\Billing\Events\BillingTenantPurgedEvent;
use Domain\Billing\Events\BillingTenantUnlockedEvent;
use Domain\Billing\Listeners\BillingCollectionSentListener;
use Domain\Billing\Listeners\BillingPurgeWarningListener;
use Domain\Billing\Listeners\BillingTenantGraceListener;
use Domain\Billing\Listeners\BillingTenantLockedListener;
use Domain\Billing\Listeners\BillingTenantPurgedListener;
use Domain\Billing\Listeners\BillingTenantUnlockedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Testa que os listeners de auditoria de eventos Billing críticos
 * estão registrados no EventServiceProvider e escrevem logs corretamente.
 */
final class BillingCriticalEventListenersTest extends TestCase
{
    // -----------------------------------------------------------------------
    // BillingTenantLockedEvent
    // -----------------------------------------------------------------------

    public function test_billing_tenant_locked_event_has_listener_registered(): void
    {
        Event::assertListening(
            BillingTenantLockedEvent::class,
            BillingTenantLockedListener::class,
        );
    }

    public function test_billing_tenant_locked_listener_logs_event(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'billing.tenant_locked'
                    && isset($context['tenant_id'], $context['reason'], $context['locked_at']);
            });

        $tenantId = (string) Str::orderedUuid();

        app(BillingTenantLockedListener::class)->handle(
            new BillingTenantLockedEvent(
                tenantId: $tenantId,
                reason: 'invoice_overdue',
                lockedAt: now()->toIso8601String(),
            )
        );
    }

    // -----------------------------------------------------------------------
    // BillingTenantUnlockedEvent
    // -----------------------------------------------------------------------

    public function test_billing_tenant_unlocked_event_has_listener_registered(): void
    {
        Event::assertListening(
            BillingTenantUnlockedEvent::class,
            BillingTenantUnlockedListener::class,
        );
    }

    public function test_billing_tenant_unlocked_listener_logs_event(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'billing.tenant_unlocked'
                    && isset($context['tenant_id'], $context['unlocked_at']);
            });

        app(BillingTenantUnlockedListener::class)->handle(
            new BillingTenantUnlockedEvent(
                tenantId: (string) Str::orderedUuid(),
                unlockedAt: now()->toIso8601String(),
            )
        );
    }

    // -----------------------------------------------------------------------
    // BillingTenantGraceEvent
    // -----------------------------------------------------------------------

    public function test_billing_tenant_grace_event_has_listener_registered(): void
    {
        Event::assertListening(
            BillingTenantGraceEvent::class,
            BillingTenantGraceListener::class,
        );
    }

    public function test_billing_tenant_grace_listener_logs_event(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'billing.tenant_grace'
                    && isset(
                        $context['tenant_id'],
                        $context['invoice_id'],
                        $context['grace_deadline'],
                        $context['days_overdue'],
                    );
            });

        app(BillingTenantGraceListener::class)->handle(
            new BillingTenantGraceEvent(
                tenantId: (string) Str::orderedUuid(),
                invoiceId: (string) Str::orderedUuid(),
                graceDeadline: now()->addDays(7)->toIso8601String(),
                daysOverdue: 3,
            )
        );
    }

    // -----------------------------------------------------------------------
    // BillingTenantPurgedEvent
    // -----------------------------------------------------------------------

    public function test_billing_tenant_purged_event_has_listener_registered(): void
    {
        Event::assertListening(
            BillingTenantPurgedEvent::class,
            BillingTenantPurgedListener::class,
        );
    }

    public function test_billing_tenant_purged_listener_logs_event(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'billing.tenant_purged'
                    && isset($context['tenant_id'], $context['report_id'], $context['purged_at']);
            });

        app(BillingTenantPurgedListener::class)->handle(
            new BillingTenantPurgedEvent(
                tenantId: (string) Str::orderedUuid(),
                reportId: (string) Str::orderedUuid(),
                purgedAt: now()->toIso8601String(),
            )
        );
    }

    // -----------------------------------------------------------------------
    // BillingPurgeWarningEvent
    // -----------------------------------------------------------------------

    public function test_billing_purge_warning_event_has_listener_registered(): void
    {
        Event::assertListening(
            BillingPurgeWarningEvent::class,
            BillingPurgeWarningListener::class,
        );
    }

    public function test_billing_purge_warning_listener_logs_event(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'billing.purge_warning'
                    && isset($context['tenant_id'], $context['purge_deadline']);
            });

        app(BillingPurgeWarningListener::class)->handle(
            new BillingPurgeWarningEvent(
                tenantId: (string) Str::orderedUuid(),
                purgeDeadline: now()->addDays(3)->toIso8601String(),
            )
        );
    }

    // -----------------------------------------------------------------------
    // BillingCollectionSentEvent
    // -----------------------------------------------------------------------

    public function test_billing_collection_sent_event_has_listener_registered(): void
    {
        Event::assertListening(
            BillingCollectionSentEvent::class,
            BillingCollectionSentListener::class,
        );
    }

    public function test_billing_collection_sent_listener_logs_event(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'billing.collection_sent'
                    && isset(
                        $context['tenant_id'],
                        $context['invoice_id'],
                        $context['template_id'],
                        $context['channel'],
                        $context['recipient'],
                        $context['status'],
                    );
            });

        app(BillingCollectionSentListener::class)->handle(
            new BillingCollectionSentEvent(
                tenantId: (string) Str::orderedUuid(),
                invoiceId: (string) Str::orderedUuid(),
                templateId: (string) Str::orderedUuid(),
                channel: 'whatsapp',
                recipient: '+5511999999999',
                status: 'sent',
            )
        );
    }
}
