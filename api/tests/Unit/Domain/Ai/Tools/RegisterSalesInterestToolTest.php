<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\Models\AiSellerNotification;
use Domain\Ai\Tools\RegisterSalesInterestTool;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationTask;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class RegisterSalesInterestToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_registers_interest_and_sends_customer_message(): void
    {
        Event::fake();

        $negotiation = CRMNegotiation::factory()->create();
        $seller = AuthUser::factory()->create([
            'tenant_id' => $negotiation->tenant_id,
            'name' => 'Rosa Comercial',
        ]);
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $negotiation->tenant_id,
            'contact_id' => $negotiation->crm_contact_id,
            'assigned_to' => $seller->id,
        ]);

        $tool = app(RegisterSalesInterestTool::class);
        $result = $tool->handle(new ToolInputDTO(
            toolName: 'register_sales_interest',
            parameters: [
                'ticket_id' => (string) $ticket->id,
                'seller' => 'Rosa Comercial',
                'plan' => 'Business',
                'team_size' => 8,
                'message_to_customer' => 'Perfeito, vou acionar um especialista.',
            ],
            context: ['tenant_id' => (string) $negotiation->tenant_id],
        ));

        expect($result->success)->toBeTrue();
        expect($result->data['seller_id'])->toBe((string) $seller->id);
        expect($result->data['negotiation_id'])->toBe((string) $negotiation->id);
        expect(AiSellerNotification::query()->count())->toBe(1);
        expect(CRMNegotiationTask::query()->count())->toBe(1);

        $message = ChatMessage::query()->find($result->data['message_id']);
        expect($message)->not->toBeNull();
        expect($message->content)->toBe('Perfeito, vou acionar um especialista.');
        expect($message->source)->toBe('ai');
    }

    public function test_it_creates_negotiation_linked_to_contact_when_none_exists(): void
    {
        Event::fake();

        $contact = CRMContact::factory()->create();
        $seller = AuthUser::factory()->create([
            'tenant_id' => $contact->tenant_id,
            'name' => 'Rosa Comercial',
        ]);
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $contact->tenant_id,
            'contact_id' => $contact->id,
            'assigned_to' => $seller->id,
        ]);

        $tool = app(RegisterSalesInterestTool::class);
        $result = $tool->handle(new ToolInputDTO(
            toolName: 'register_sales_interest',
            parameters: [
                'ticket_id' => (string) $ticket->id,
                'plan' => 'Professional',
                'team_size' => 8,
                'message_to_customer' => 'Perfeito, vou acionar um especialista.',
            ],
            context: ['tenant_id' => (string) $contact->tenant_id],
        ));

        expect($result->success)->toBeTrue();
        expect($result->data['negotiation_created'])->toBeTrue();

        $negotiation = CRMNegotiation::query()->find($result->data['negotiation_id']);
        expect($negotiation)->not->toBeNull();
        expect($negotiation->crm_contact_id)->toBe((string) $contact->id);
        expect($negotiation->tenant_id)->toBe((string) $contact->tenant_id);
        expect($negotiation->title)->toContain('Professional');
    }
}
