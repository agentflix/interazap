<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Jobs;

use Domain\Ai\Enums\AiPostSaleScheduleType;
use Domain\Ai\Enums\AiPostSaleStatus;
use Domain\Ai\Jobs\AiPostSaleFollowUpJob;
use Domain\Ai\Models\AiPostSaleSchedule;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Infrastructure\WhatsApp\Contracts\WhatsAppAdapterInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * @group ai
 * @group jobs
 */
class AiPostSaleFollowUpJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformTenant $tenant;

    private CRMContact $contact;

    private CRMNegotiation $negotiation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = PlatformTenant::factory()->create();

        // Create a WhatsApp instance
        ChatInstance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+5511999999999',
        ]);

        $this->negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_contact_id' => $this->contact->id,
            'status' => 'won',
        ]);
    }

    public function test_it_sends_message_successfully(): void
    {
        $schedule = AiPostSaleSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'negotiation_id' => $this->negotiation->id,
            'schedule_type' => AiPostSaleScheduleType::D1,
            'status' => AiPostSaleStatus::PENDING,
            'scheduled_at' => now()->subHour(),
        ]);

        $mockWhatsApp = Mockery::mock(WhatsAppAdapterInterface::class);
        $mockWhatsApp->shouldReceive('sendTextMessage')
            ->once()
            ->andReturn(['success' => true, 'message_id' => 'msg_123']);

        $this->app->instance(WhatsAppAdapterInterface::class, $mockWhatsApp);

        $job = new AiPostSaleFollowUpJob;
        $job->handle($mockWhatsApp);

        $schedule->refresh();
        expect($schedule->status)->toBe(AiPostSaleStatus::SENT);
        expect($schedule->message_id)->toBe('msg_123');
        expect($schedule->sent_at)->not->toBeNull();
    }

    public function test_it_skips_when_customer_replied(): void
    {
        // Create an active ticket with recent reply
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'status' => 'closed',
        ]);

        ChatMessage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ticket_id' => $ticket->id,
            'direction' => 'incoming',
            'created_at' => now()->subHours(2),
        ]);

        $schedule = AiPostSaleSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'negotiation_id' => $this->negotiation->id,
            'schedule_type' => AiPostSaleScheduleType::D1,
            'status' => AiPostSaleStatus::PENDING,
            'scheduled_at' => now()->subHour(),
        ]);

        $mockWhatsApp = Mockery::mock(WhatsAppAdapterInterface::class);
        $mockWhatsApp->shouldNotReceive('sendTextMessage');

        $this->app->instance(WhatsAppAdapterInterface::class, $mockWhatsApp);

        $job = new AiPostSaleFollowUpJob;
        $job->handle($mockWhatsApp);

        $schedule->refresh();
        expect($schedule->status)->toBe(AiPostSaleStatus::SKIPPED);
        expect($schedule->error_message)->toContain('replied recently');
    }

    public function test_it_skips_when_negotiation_status_changed(): void
    {
        // Change negotiation status from WON
        $this->negotiation->update(['status' => 'lost']);

        $schedule = AiPostSaleSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'negotiation_id' => $this->negotiation->id,
            'schedule_type' => AiPostSaleScheduleType::D1,
            'status' => AiPostSaleStatus::PENDING,
            'scheduled_at' => now()->subHour(),
        ]);

        $mockWhatsApp = Mockery::mock(WhatsAppAdapterInterface::class);
        $mockWhatsApp->shouldNotReceive('sendTextMessage');

        $this->app->instance(WhatsAppAdapterInterface::class, $mockWhatsApp);

        $job = new AiPostSaleFollowUpJob;
        $job->handle($mockWhatsApp);

        $schedule->refresh();
        expect($schedule->status)->toBe(AiPostSaleStatus::SKIPPED);
        expect($schedule->error_message)->toContain('status changed');
    }

    public function test_it_skips_when_active_ticket_exists(): void
    {
        // Create an active ticket
        ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'status' => 'open',
        ]);

        $schedule = AiPostSaleSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'negotiation_id' => $this->negotiation->id,
            'schedule_type' => AiPostSaleScheduleType::D1,
            'status' => AiPostSaleStatus::PENDING,
            'scheduled_at' => now()->subHour(),
        ]);

        $mockWhatsApp = Mockery::mock(WhatsAppAdapterInterface::class);
        $mockWhatsApp->shouldNotReceive('sendTextMessage');

        $this->app->instance(WhatsAppAdapterInterface::class, $mockWhatsApp);

        $job = new AiPostSaleFollowUpJob;
        $job->handle($mockWhatsApp);

        $schedule->refresh();
        expect($schedule->status)->toBe(AiPostSaleStatus::SKIPPED);
        expect($schedule->error_message)->toContain('Active ticket');
    }

    public function test_it_marks_as_failed_on_error(): void
    {
        $schedule = AiPostSaleSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'negotiation_id' => $this->negotiation->id,
            'schedule_type' => AiPostSaleScheduleType::D1,
            'status' => AiPostSaleStatus::PENDING,
            'scheduled_at' => now()->subHour(),
        ]);

        $mockWhatsApp = Mockery::mock(WhatsAppAdapterInterface::class);
        $mockWhatsApp->shouldReceive('sendTextMessage')
            ->once()
            ->andReturn(['success' => false, 'error' => 'Connection failed']);

        $this->app->instance(WhatsAppAdapterInterface::class, $mockWhatsApp);

        $job = new AiPostSaleFollowUpJob;
        $job->handle($mockWhatsApp);

        $schedule->refresh();
        expect($schedule->status)->toBe(AiPostSaleStatus::FAILED);
        expect($schedule->error_message)->toBe('Connection failed');
    }
}
