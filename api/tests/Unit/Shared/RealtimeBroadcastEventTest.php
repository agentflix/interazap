<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Domain\Shared\Events\RealtimeBroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class RealtimeBroadcastEventTest extends TestCase
{
    public function test_exposes_channels_event_name_and_payload(): void
    {
        $event = new RealtimeBroadcastEvent(
            channels: ['tenant.1', 'ticket.2'],
            eventName: 'ticket.updated',
            payload: ['status' => 'open', 'message' => 'ok'],
        );

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-tenant.1', $channels[0]->name);
        $this->assertSame('private-ticket.2', $channels[1]->name);
        $this->assertSame('ticket.updated', $event->broadcastAs());
        $this->assertSame(['status' => 'open', 'message' => 'ok'], $event->broadcastWith());
    }
}
