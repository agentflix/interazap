<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use Carbon\CarbonImmutable;
use Domain\Auth\Models\AuthUser;
use Domain\CRM\Actions\CRMEventActions;
use Domain\CRM\DTOs\CRMEventDTO;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMEvent;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CRMEventActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CRMEventActions $actions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actions = new CRMEventActions;
    }

    public function test_create_with_links_participants_and_reminders(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);

        $dto = CRMEventDTO::fromArray($tenant->id, [
            'title' => 'Weekly Sync',
            'starts_at' => now()->addDay()->toIso8601String(),
            'links' => [
                ['type' => 'contact', 'id' => $contact->id],
            ],
            'participants' => [
                ['auth_user_id' => $user->id, 'is_organizer' => true],
            ],
            'reminders' => [
                ['minutes_before' => 30, 'notify_email' => true],
            ],
        ]);

        $event = $this->actions->create($dto);

        $this->assertSame($tenant->id, $event->tenant_id);
        $this->assertCount(1, $event->links);
        $this->assertCount(1, $event->participants);
        $this->assertCount(1, $event->reminders);
    }

    public function test_update_replaces_links_participants_and_reminders(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $contactA = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $contactB = CRMContact::factory()->create(['tenant_id' => $tenant->id]);

        $event = $this->actions->create(CRMEventDTO::fromArray($tenant->id, [
            'title' => 'Initial',
            'starts_at' => now()->addDay()->toIso8601String(),
            'links' => [
                ['type' => 'contact', 'id' => $contactA->id],
            ],
            'participants' => [
                ['auth_user_id' => $user->id, 'is_organizer' => true],
            ],
            'reminders' => [
                ['minutes_before' => 15, 'notify_email' => true],
            ],
        ]));

        $updated = $this->actions->update($tenant->id, (string) $event->id, CRMEventDTO::fromArray($tenant->id, [
            'id' => $event->id,
            'title' => 'Updated',
            'starts_at' => now()->addDays(2)->toIso8601String(),
            'links' => [
                ['type' => 'contact', 'id' => $contactB->id],
            ],
            'participants' => [],
            'reminders' => [
                ['minutes_before' => 60, 'notify_push' => true],
            ],
        ]));

        $this->assertSame('Updated', $updated->title);
        $this->assertCount(1, $updated->links);
        $this->assertSame($contactB->id, $updated->links->first()?->linkable_id);
        $this->assertCount(0, $updated->participants);
        $this->assertCount(1, $updated->reminders);
    }

    public function test_calendar_filters_by_user_participation(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $other = AuthUser::factory()->create(['tenant_id' => $tenant->id]);

        $event = CRMEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'auth_user_id' => $user->id,
            'starts_at' => now()->addHours(2),
        ]);

        $event->participants()->create([
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'auth_user_id' => $user->id,
            'status' => \Domain\CRM\Models\CRMEventParticipant::STATUS_PENDING,
            'is_organizer' => true,
        ]);

        $start = CarbonImmutable::now()->subDay();
        $end = CarbonImmutable::now()->addDays(2);

        $calendar = $this->actions->calendar($tenant->id, $start, $end, $user->id);
        $this->assertCount(1, $calendar);

        $calendarOther = $this->actions->calendar($tenant->id, $start, $end, $other->id);
        $this->assertCount(0, $calendarOther);
    }

    public function test_list_applies_filters(): void
    {
        $tenant = PlatformTenant::factory()->create();

        CRMEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Reunião Vendas',
            'status' => CRMEvent::STATUS_SCHEDULED,
            'type' => CRMEvent::TYPE_MEETING,
            'starts_at' => now()->addDay(),
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Ligação Suporte',
            'status' => CRMEvent::STATUS_COMPLETED,
            'type' => CRMEvent::TYPE_CALL,
            'starts_at' => now()->addDays(2),
        ]);

        $result = $this->actions->list($tenant->id, [
            'status' => CRMEvent::STATUS_SCHEDULED,
            'type' => CRMEvent::TYPE_MEETING,
        ]);

        $this->assertCount(1, $result->items());
        $this->assertSame('Reunião Vendas', $result->items()[0]->title);
    }

    public function test_linked_returns_empty_for_invalid_alias(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $result = $this->actions->linked($tenant->id, 'invalid', 'id');

        $this->assertCount(0, $result);
    }

    public function test_statistics_counts_by_status_and_type(): void
    {
        $tenant = PlatformTenant::factory()->create();

        CRMEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => CRMEvent::STATUS_SCHEDULED,
            'type' => CRMEvent::TYPE_MEETING,
            'starts_at' => now()->addDay(),
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => CRMEvent::STATUS_COMPLETED,
            'type' => CRMEvent::TYPE_CALL,
            'starts_at' => now()->addDays(2),
        ]);

        $stats = $this->actions->statistics($tenant->id, CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDays(3));

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['by_status'][CRMEvent::STATUS_SCHEDULED]);
        $this->assertSame(1, $stats['by_status'][CRMEvent::STATUS_COMPLETED]);
        $this->assertSame(1, $stats['by_type'][CRMEvent::TYPE_MEETING]);
        $this->assertSame(1, $stats['by_type'][CRMEvent::TYPE_CALL]);
        $this->assertSame(1, $stats['upcoming']);
    }

    public function test_update_status_changes_state(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $event = CRMEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => CRMEvent::STATUS_SCHEDULED,
        ]);

        $updated = $this->actions->updateStatus($tenant->id, (string) $event->id, CRMEvent::STATUS_COMPLETED);

        $this->assertSame(CRMEvent::STATUS_COMPLETED, $updated->status);
    }

    public function test_upcoming_returns_future_events_for_user(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);

        CRMEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'auth_user_id' => $user->id,
            'starts_at' => now()->addHours(3),
            'status' => CRMEvent::STATUS_SCHEDULED,
        ]);

        $events = $this->actions->upcoming($tenant->id, $user->id, 5);

        $this->assertCount(1, $events);
        $this->assertSame($user->id, $events->first()?->auth_user_id);
    }
}
