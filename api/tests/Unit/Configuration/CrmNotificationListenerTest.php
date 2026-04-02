<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Events\NegotiationLostEvent;
use Domain\Configuration\Events\NegotiationWonEvent;
use Domain\CRM\Models\CRMNegotiation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CrmNotificationListenerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_notifications_for_negotiation_outcome_events(): void
    {
        Queue::fake();

        $user = AuthUser::factory()->create();
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $user->tenant_id,
            'title' => 'Proposta Enterprise',
            'amount' => 1500.00,
        ]);

        Event::dispatch(new NegotiationWonEvent(
            tenantId: (string) $negotiation->tenant_id,
            negotiationId: (string) $negotiation->id,
            title: (string) $negotiation->title,
            amount: (float) $negotiation->amount,
        ));

        Event::dispatch(new NegotiationLostEvent(
            tenantId: (string) $negotiation->tenant_id,
            negotiationId: (string) $negotiation->id,
            title: (string) $negotiation->title,
            amount: (float) $negotiation->amount,
        ));

        $this->assertDatabaseHas('configuration_notifications', [
            'tenant_id' => $negotiation->tenant_id,
            'type' => 'system',
            'title' => 'Negociação ganha',
        ]);

        $this->assertDatabaseHas('configuration_notifications', [
            'tenant_id' => $negotiation->tenant_id,
            'type' => 'system',
            'title' => 'Negociação perdida',
        ]);
    }
}
