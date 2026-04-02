<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Ai\Events\AiRunRequested;
use Domain\Chat\Services\ChatAutopilotResponder;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class ChatAutopilotResponderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_responder_dispatches_ai_run_requested_event(): void
    {
        Event::fake();

        $tenant = PlatformTenant::factory()->create();

        $responder = new ChatAutopilotResponder;
        $responder->respond($tenant->id, 'ticket-1', 'Olá', [
            'instance_id' => 'instance-1',
        ]);

        Event::assertDispatched(AiRunRequested::class, fn (AiRunRequested $event): bool => $event->tenantId === $tenant->id
            && $event->ticketId === 'ticket-1'
            && $event->body === 'Olá'
            && ($event->context['instance_id'] ?? null) === 'instance-1');
    }

    public function test_responder_dispatches_even_without_active_playbook(): void
    {
        Event::fake();

        $tenant = PlatformTenant::factory()->create();

        $responder = new ChatAutopilotResponder;
        $responder->respond($tenant->id, 'ticket-1', 'Olá', []);

        Event::assertDispatched(AiRunRequested::class);
    }
}
