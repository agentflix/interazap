<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Models;

use Domain\Ai\Enums\AiPostSaleScheduleType;
use Domain\Ai\Enums\AiPostSaleStatus;
use Domain\Ai\Models\AiPostSaleSchedule;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group post-sales
 */
class AiPostSaleScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_can_be_created_with_factory(): void
    {
        $schedule = AiPostSaleSchedule::factory()->create();

        expect($schedule)->toBeInstanceOf(AiPostSaleSchedule::class);
        expect($schedule->id)->toBeString();
        expect($schedule->tenant_id)->toBeString();
    }

    public function test_it_has_correct_table_name(): void
    {
        $schedule = new AiPostSaleSchedule;
        expect($schedule->getTable())->toBe('ai_post_sale_schedules');
    }

    public function test_it_casts_schedule_type_to_enum(): void
    {
        $schedule = AiPostSaleSchedule::factory()->create([
            'schedule_type' => 'd1',
        ]);

        expect($schedule->schedule_type)->toBe(AiPostSaleScheduleType::D1);
    }

    public function test_it_casts_status_to_enum(): void
    {
        $schedule = AiPostSaleSchedule::factory()->create([
            'status' => 'pending',
        ]);

        expect($schedule->status)->toBe(AiPostSaleStatus::PENDING);
    }

    public function test_it_belongs_to_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $schedule = AiPostSaleSchedule::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        expect($schedule->tenant)->toBeInstanceOf(PlatformTenant::class);
        expect($schedule->tenant->id)->toBe($tenant->id);
    }

    public function test_it_belongs_to_negotiation(): void
    {
        $negotiation = CRMNegotiation::factory()->create();
        $schedule = AiPostSaleSchedule::factory()->create([
            'negotiation_id' => $negotiation->id,
            'tenant_id' => $negotiation->tenant_id,
        ]);

        expect($schedule->negotiation)->toBeInstanceOf(CRMNegotiation::class);
        expect($schedule->negotiation->id)->toBe($negotiation->id);
    }

    public function test_it_can_have_related_ticket(): void
    {
        $ticket = ChatTicket::factory()->create();
        $schedule = AiPostSaleSchedule::factory()->create([
            'ticket_id' => $ticket->id,
            'tenant_id' => $ticket->tenant_id,
        ]);

        expect($schedule->ticket)->toBeInstanceOf(ChatTicket::class);
        expect($schedule->ticket->id)->toBe($ticket->id);
    }

    public function test_it_calculates_scheduled_at_from_sale_date(): void
    {
        $saleDate = today();

        // Let the model auto-calculate scheduled_at
        $d1 = AiPostSaleSchedule::factory()->create([
            'schedule_type' => 'd1',
            'sale_date' => $saleDate,
            'scheduled_at' => null, // Force re-calculation
        ]);

        $d7 = AiPostSaleSchedule::factory()->create([
            'schedule_type' => 'd7',
            'sale_date' => $saleDate,
            'scheduled_at' => null,
        ]);

        $d30 = AiPostSaleSchedule::factory()->create([
            'schedule_type' => 'd30',
            'sale_date' => $saleDate,
            'scheduled_at' => null,
        ]);

        expect($d1->scheduled_at->toDateString())
            ->toBe($saleDate->copy()->addDay()->toDateString());
        expect($d7->scheduled_at->toDateString())
            ->toBe($saleDate->copy()->addDays(7)->toDateString());
        expect($d30->scheduled_at->toDateString())
            ->toBe($saleDate->copy()->addDays(30)->toDateString());
    }

    public function test_it_scopes_pending_schedules(): void
    {
        AiPostSaleSchedule::factory()->create(['status' => 'pending']);
        AiPostSaleSchedule::factory()->create(['status' => 'sent']);
        AiPostSaleSchedule::factory()->create(['status' => 'failed']);

        $pending = AiPostSaleSchedule::pending()->get();

        expect($pending)->toHaveCount(1);
    }

    public function test_it_scopes_due_schedules(): void
    {
        // Due: scheduled_at in the past
        AiPostSaleSchedule::factory()->create([
            'status' => 'pending',
            'scheduled_at' => now()->subHour(),
        ]);

        // Not due: scheduled_at in the future
        AiPostSaleSchedule::factory()->create([
            'status' => 'pending',
            'scheduled_at' => now()->addHour(),
        ]);

        // Not due: already sent
        AiPostSaleSchedule::factory()->create([
            'status' => 'sent',
            'scheduled_at' => now()->subHour(),
        ]);

        $due = AiPostSaleSchedule::due()->get();

        expect($due)->toHaveCount(1);
    }

    public function test_it_marks_as_sent(): void
    {
        $schedule = AiPostSaleSchedule::factory()->create([
            'status' => 'pending',
            'sent_at' => null,
        ]);

        $schedule->markAsSent('msg123');

        expect($schedule->refresh()->status)->toBe(AiPostSaleStatus::SENT);
        expect($schedule->sent_at)->not->toBeNull();
        expect($schedule->message_id)->toBe('msg123');
    }

    public function test_it_marks_as_failed(): void
    {
        $schedule = AiPostSaleSchedule::factory()->create([
            'status' => 'pending',
        ]);

        $schedule->markAsFailed('Connection error');

        expect($schedule->refresh()->status)->toBe(AiPostSaleStatus::FAILED);
        expect($schedule->error_message)->toBe('Connection error');
    }

    public function test_it_can_be_cancelled(): void
    {
        $schedule = AiPostSaleSchedule::factory()->create([
            'status' => 'pending',
        ]);

        $schedule->cancel();

        expect($schedule->refresh()->status)->toBe(AiPostSaleStatus::CANCELLED);
    }
}
