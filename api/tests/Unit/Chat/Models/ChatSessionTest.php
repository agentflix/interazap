<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Models;

use Domain\Chat\Models\ChatSession;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class ChatSessionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = (string) PlatformTenant::factory()->create()->id;
    }

    public function test_creates_chat_session_with_uuid_primary_key(): void
    {
        $ticket = ChatTicket::factory()->create(['tenant_id' => $this->tenantId]);
        $contact = CRMContact::factory()->create(['tenant_id' => $this->tenantId]);

        $session = ChatSession::query()->create([
            'tenant_id' => $this->tenantId,
            'contact_id' => $contact->id,
            'ticket_id' => $ticket->id,
            'client_info' => ['browser' => 'Chrome'],
        ]);

        $this->assertNotEmpty($session->id);
        $this->assertNotEmpty($session->token);
        $this->assertEquals(['browser' => 'Chrome'], $session->client_info);
        $this->assertNull($session->last_activity_at);
    }

    public function test_finds_chat_session_by_id(): void
    {
        $ticket = ChatTicket::factory()->create(['tenant_id' => $this->tenantId]);

        $session = ChatSession::query()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
        ]);

        $found = \Domain\Chat\Models\ChatSession::query()->find($session->id);

        $this->assertNotNull($found);
        $this->assertEquals($session->id, $found->id);
        $this->assertEquals($ticket->id, $found->ticket_id);
    }

    public function test_updates_last_activity_at_via_touch_last_activity(): void
    {
        $ticket = ChatTicket::factory()->create(['tenant_id' => $this->tenantId]);

        $session = ChatSession::query()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
        ]);

        $this->assertNull($session->last_activity_at);

        $session->touchLastActivity();

        $this->assertNotNull($session->last_activity_at);
        $this->assertTrue($session->last_activity_at->isAfter(now()->subSecond()));
    }

    public function test_belongs_to_tenant_scope(): void
    {
        PlatformTenant::factory()->create()->id;
        $ticket = ChatTicket::factory()->create(['tenant_id' => $this->tenantId]);

        $session = ChatSession::query()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
        ]);

        $found = \Domain\Chat\Models\ChatSession::query()->find($session->id);
        $this->assertEquals($this->tenantId, $found->tenant_id);

        $foundWithoutScope = ChatSession::query()->forTenant($this->tenantId)->find($session->id);
        $this->assertNotNull($foundWithoutScope);
    }

    public function test_has_relationships_with_ticket_and_contact(): void
    {
        $ticket = ChatTicket::factory()->create(['tenant_id' => $this->tenantId]);
        $contact = CRMContact::factory()->create(['tenant_id' => $this->tenantId]);

        $session = ChatSession::query()->create([
            'tenant_id' => $this->tenantId,
            'contact_id' => $contact->id,
            'ticket_id' => $ticket->id,
        ]);

        $session->load(['ticket', 'contact']);

        $this->assertNotNull($session->ticket);
        $this->assertEquals($ticket->id, $session->ticket->id);
        $this->assertNotNull($session->contact);
        $this->assertEquals($contact->id, $session->contact->id);
    }
}
