<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use Domain\Ai\Events\AiRunRequested;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\Ai\Listeners\AiGateKeeperListener;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class AiGateKeeperListenerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dispatches_autopilot_trigger_when_plan_has_ai_enabled_and_ticket_is_not_under_human_takeover(): void
    {
        $plan = PlatformPlan::factory()->create(['ai_enabled' => true]);
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'human_takeover_at' => null,
        ]);

        Event::fake([AutopilotTriggerFired::class]);

        app(AiGateKeeperListener::class)->handle(new AiRunRequested(
            tenantId: (string) $tenant->id,
            ticketId: (string) $ticket->id,
            body: 'Olá IA',
            context: [
                'message_id' => 'msg-123',
                'instance_id' => 'instance-123',
            ],
        ));

        Event::assertDispatched(AutopilotTriggerFired::class, fn (AutopilotTriggerFired $event): bool => $event->tenantId === (string) $tenant->id
            && ($event->context['ticket_id'] ?? '') === (string) $ticket->id
            && ($event->context['message_id'] ?? '') === 'msg-123'
            && ($event->context['instance_id'] ?? '') === 'instance-123'
            && ($event->context['body'] ?? '') === 'Olá IA');
    }

    public function test_does_not_dispatch_when_ticket_is_under_human_takeover(): void
    {
        $plan = PlatformPlan::factory()->create(['ai_enabled' => true]);
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'human_takeover_at' => now(),
        ]);

        Event::fake([AutopilotTriggerFired::class]);

        app(AiGateKeeperListener::class)->handle(new AiRunRequested(
            tenantId: (string) $tenant->id,
            ticketId: (string) $ticket->id,
            body: 'Não deve disparar',
            context: ['message_id' => 'msg-ht'],
        ));

        Event::assertNotDispatched(AutopilotTriggerFired::class);
    }

    public function test_does_not_dispatch_when_tenant_plan_has_ai_disabled(): void
    {
        $plan = PlatformPlan::factory()->create(['ai_enabled' => false]);
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'human_takeover_at' => null,
        ]);

        Event::fake([AutopilotTriggerFired::class]);

        app(AiGateKeeperListener::class)->handle(new AiRunRequested(
            tenantId: (string) $tenant->id,
            ticketId: (string) $ticket->id,
            body: 'Sem IA',
            context: ['message_id' => 'msg-no-ai'],
        ));

        Event::assertNotDispatched(AutopilotTriggerFired::class);
    }
}
