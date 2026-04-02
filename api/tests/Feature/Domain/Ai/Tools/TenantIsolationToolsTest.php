<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Tools;

use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\Tools\CloseTicketTool;
use Domain\Ai\Tools\CreateNoteTool;
use Domain\Ai\Tools\GetContactInfoTool;
use Domain\Ai\Tools\MovePipelineTool;
use Domain\Ai\Tools\ReadTicketTool;
use Domain\Ai\Tools\SendMessageTool;
use Domain\Ai\Tools\TransferToHumanTool;
use Domain\Ai\Tools\UpdateContactTagsTool;
use Domain\Ai\Tools\UpdateLeadScoreTool;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Tests tenant isolation for all AI Tools.
 *
 * Security requirement: Tools must only access data belonging to the same tenant.
 *
 * @group ai
 * @group tools
 * @group security
 */
class TenantIsolationToolsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformTenant $tenantA;

    private PlatformTenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two tenants for cross-tenant tests
        $this->tenantA = PlatformTenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = PlatformTenant::factory()->create(['name' => 'Tenant B']);
    }

    // ============ GetContactInfoTool ============

    public function test_get_contact_info_returns_contact_for_same_tenant(): void
    {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'John Doe',
        ]);

        $tool = new GetContactInfoTool;
        $input = new ToolInputDTO(
            toolName: 'get_contact_info',
            parameters: ['contact_id' => $contact->id],
            context: ['tenant_id' => $this->tenantA->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeTrue();
        expect($result->data['contact']['name'])->toBe('John Doe');
    }

    public function test_get_contact_info_rejects_cross_tenant_access(): void
    {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $tool = new GetContactInfoTool;
        $input = new ToolInputDTO(
            toolName: 'get_contact_info',
            parameters: ['contact_id' => $contact->id],
            context: ['tenant_id' => $this->tenantB->id], // Different tenant
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('not found');
    }

    // ============ UpdateLeadScoreTool ============

    public function test_update_lead_score_works_for_same_tenant(): void
    {
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'lead_score' => 50,
        ]);

        $tool = new UpdateLeadScoreTool;
        $input = new ToolInputDTO(
            toolName: 'update_lead_score',
            parameters: ['negotiation_id' => $negotiation->id, 'score' => 85],
            context: ['tenant_id' => $this->tenantA->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeTrue();
        expect($result->data['new_score'])->toBe(85);
    }

    public function test_update_lead_score_rejects_cross_tenant_access(): void
    {
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $tool = new UpdateLeadScoreTool;
        $input = new ToolInputDTO(
            toolName: 'update_lead_score',
            parameters: ['negotiation_id' => $negotiation->id, 'score' => 85],
            context: ['tenant_id' => $this->tenantB->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('not found');
    }

    // ============ MovePipelineTool ============

    public function test_move_pipeline_works_for_same_tenant(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $tool = new MovePipelineTool;
        $input = new ToolInputDTO(
            toolName: 'move_pipeline',
            parameters: ['negotiation_id' => $negotiation->id, 'step_id' => $step->id],
            context: ['tenant_id' => $this->tenantA->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeTrue();
    }

    public function test_move_pipeline_rejects_cross_tenant_negotiation(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $tool = new MovePipelineTool;
        $input = new ToolInputDTO(
            toolName: 'move_pipeline',
            parameters: ['negotiation_id' => $negotiation->id, 'step_id' => $step->id],
            context: ['tenant_id' => $this->tenantB->id], // Different tenant
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('not found');
    }

    // ============ CloseTicketTool ============

    public function test_close_ticket_works_for_same_tenant(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'status' => 'open',
        ]);

        $tool = new CloseTicketTool;
        $input = new ToolInputDTO(
            toolName: 'close_ticket',
            parameters: ['ticket_id' => $ticket->id, 'reason' => 'resolved'],
            context: ['tenant_id' => $this->tenantA->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeTrue();
        expect($result->data['status'])->toBe('closed');
    }

    public function test_close_ticket_rejects_cross_tenant_access(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'status' => 'open',
        ]);

        $tool = new CloseTicketTool;
        $input = new ToolInputDTO(
            toolName: 'close_ticket',
            parameters: ['ticket_id' => $ticket->id, 'reason' => 'resolved'],
            context: ['tenant_id' => $this->tenantB->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('not found');
    }

    // ============ ReadTicketTool ============

    public function test_read_ticket_works_for_same_tenant(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $tool = new ReadTicketTool;
        $input = new ToolInputDTO(
            toolName: 'read_ticket',
            parameters: ['ticket_id' => $ticket->id],
            context: ['tenant_id' => $this->tenantA->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeTrue();
    }

    public function test_read_ticket_rejects_cross_tenant_access(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $tool = new ReadTicketTool;
        $input = new ToolInputDTO(
            toolName: 'read_ticket',
            parameters: ['ticket_id' => $ticket->id],
            context: ['tenant_id' => $this->tenantB->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeFalse();
    }

    // ============ TransferToHumanTool ============

    public function test_transfer_to_human_works_for_same_tenant(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'is_bot_active' => true,
        ]);

        $tool = new TransferToHumanTool;
        $input = new ToolInputDTO(
            toolName: 'transfer_to_human',
            parameters: ['ticket_id' => $ticket->id, 'reason' => 'customer request'],
            context: ['tenant_id' => $this->tenantA->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeTrue();
    }

    public function test_transfer_to_human_rejects_cross_tenant_access(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $tool = new TransferToHumanTool;
        $input = new ToolInputDTO(
            toolName: 'transfer_to_human',
            parameters: ['ticket_id' => $ticket->id, 'reason' => 'customer request'],
            context: ['tenant_id' => $this->tenantB->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeFalse();
    }

    // ============ UpdateContactTagsTool ============

    public function test_update_contact_tags_works_for_same_tenant(): void
    {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $tool = new UpdateContactTagsTool;
        $input = new ToolInputDTO(
            toolName: 'update_contact_tags',
            parameters: ['contact_id' => $contact->id, 'tags' => ['vip', 'lead'], 'action' => 'add'],
            context: ['tenant_id' => $this->tenantA->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeTrue();
        expect($result->data['new_tags'])->toContain('vip');
    }

    public function test_update_contact_tags_rejects_cross_tenant_access(): void
    {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $tool = new UpdateContactTagsTool;
        $input = new ToolInputDTO(
            toolName: 'update_contact_tags',
            parameters: ['contact_id' => $contact->id, 'tags' => ['vip']],
            context: ['tenant_id' => $this->tenantB->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeFalse();
    }

    // ============ CreateNoteTool ============

    public function test_create_note_works_for_same_tenant(): void
    {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $tool = new CreateNoteTool;
        $input = new ToolInputDTO(
            toolName: 'create_note',
            parameters: [
                'entity_type' => 'contact',
                'entity_id' => $contact->id,
                'content' => 'Test note content',
            ],
            context: ['tenant_id' => $this->tenantA->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeTrue();
    }

    public function test_create_note_rejects_cross_tenant_access(): void
    {
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $tool = new CreateNoteTool;
        $input = new ToolInputDTO(
            toolName: 'create_note',
            parameters: [
                'entity_type' => 'contact',
                'entity_id' => $contact->id,
                'content' => 'Test note content',
            ],
            context: ['tenant_id' => $this->tenantB->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeFalse();
    }

    // ============ SendMessageTool ============

    public function test_send_message_works_for_same_tenant(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'status' => 'open',
        ]);

        /** @var SendMessageTool $tool */
        $tool = app(SendMessageTool::class);
        $input = new ToolInputDTO(
            toolName: 'send_message',
            parameters: [
                'ticket_id' => $ticket->id,
                'content' => 'Hello, how can I help?',
            ],
            context: ['tenant_id' => $this->tenantA->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeTrue();
    }

    public function test_send_message_rejects_cross_tenant_access(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'status' => 'open',
        ]);

        /** @var SendMessageTool $tool */
        $tool = app(SendMessageTool::class);
        $input = new ToolInputDTO(
            toolName: 'send_message',
            parameters: [
                'ticket_id' => $ticket->id,
                'content' => 'Hello, how can I help?',
            ],
            context: ['tenant_id' => $this->tenantB->id],
        );

        $result = $tool->handle($input);

        expect($result->success)->toBeFalse();
    }
}
