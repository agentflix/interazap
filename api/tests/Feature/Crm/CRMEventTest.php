<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMEvent;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
});

describe('index', function (): void {
    test('can list events', function (): void {
        CRMEvent::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/crm/events');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    test('filters events by status', function (): void {
        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => CRMEvent::STATUS_SCHEDULED,
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => CRMEvent::STATUS_COMPLETED,
        ]);

        $response = $this->getJson('/api/crm/events?status=scheduled');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('filters events by type', function (): void {
        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => CRMEvent::TYPE_MEETING,
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => CRMEvent::TYPE_CALL,
        ]);

        $response = $this->getJson('/api/crm/events?type=meeting');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('filters events by date range', function (): void {
        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'starts_at' => now()->addDays(2),
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'starts_at' => now()->addDays(10),
        ]);

        $response = $this->getJson('/api/crm/events?start_date='.now()->toDateString().'&end_date='.now()->addDays(5)->toDateString());

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('filters events by participant_id', function (): void {
        $participant = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherUser = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);

        $eventWithParticipant = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $eventWithParticipant->participants()->create([
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $participant->id,
            'is_organizer' => false,
            'status' => 'accepted',
        ]);

        $eventWithoutParticipant = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $eventWithoutParticipant->participants()->create([
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $otherUser->id,
            'is_organizer' => false,
            'status' => 'accepted',
        ]);

        $response = $this->getJson('/api/crm/events?participant_id='.$participant->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('filters events by linkable_type and linkable_id', function (): void {
        $company = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherCompany = CRMCompany::factory()->create(['tenant_id' => $this->tenant->id]);

        $eventWithLink = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $eventWithLink->links()->create([
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
            'linkable_type' => CRMCompany::class,
            'linkable_id' => $company->id,
        ]);

        $eventWithOtherLink = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $eventWithOtherLink->links()->create([
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
            'linkable_type' => CRMCompany::class,
            'linkable_id' => $otherCompany->id,
        ]);

        $response = $this->getJson('/api/crm/events?'.http_build_query([
            'linkable_type' => 'company',
            'linkable_id' => $company->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('filters events by is_all_day', function (): void {
        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_all_day' => true,
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_all_day' => false,
        ]);

        $response = $this->getJson('/api/crm/events?is_all_day=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('filters events by recurrence', function (): void {
        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'recurrence' => CRMEvent::RECURRENCE_WEEKLY,
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'recurrence' => CRMEvent::RECURRENCE_NONE,
        ]);

        $response = $this->getJson('/api/crm/events?recurrence=weekly');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('filters events by location partial match', function (): void {
        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location' => 'Sala Comercial 201',
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location' => 'Auditório Principal',
        ]);

        $response = $this->getJson('/api/crm/events?location=Comercial');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('filters events with reminders only', function (): void {
        $eventWithReminder = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $eventWithReminder->reminders()->create([
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
            'type' => 'notification',
            'minutes_before' => 30,
            'notify_ui' => true,
            'notify_email' => false,
            'notify_push' => false,
            'notify_whatsapp' => false,
            'notify_webhook' => false,
            'scheduled_at' => now()->addMinutes(30),
            'is_sent' => false,
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/crm/events?has_reminders=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('filters events without reminders only', function (): void {
        $eventWithReminder = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $eventWithReminder->reminders()->create([
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
            'type' => 'notification',
            'minutes_before' => 30,
            'notify_ui' => true,
            'notify_email' => false,
            'notify_push' => false,
            'notify_whatsapp' => false,
            'notify_webhook' => false,
            'scheduled_at' => now()->addMinutes(30),
            'is_sent' => false,
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/crm/events?has_reminders=0');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });
});

describe('store', function (): void {
    test('can create event', function (): void {
        $payload = [
            'title' => 'Weekly Sync',
            'starts_at' => now()->addDay()->toIso8601String(),
            'type' => CRMEvent::TYPE_MEETING,
        ];

        $response = $this->postJson('/api/crm/events', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['title' => 'Weekly Sync']);

        $this->assertDatabaseHas('crm_events', [
            'tenant_id' => $this->tenant->id,
            'title' => 'Weekly Sync',
            'type' => CRMEvent::TYPE_MEETING,
        ]);
    });

    test('can create event with participants', function (): void {
        $otherUser = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);

        $payload = [
            'title' => 'Team Meeting',
            'starts_at' => now()->addDay()->toIso8601String(),
            'type' => CRMEvent::TYPE_MEETING,
            'participants' => [
                ['auth_user_id' => $this->user->id, 'is_organizer' => true],
                ['auth_user_id' => $otherUser->id, 'is_organizer' => false],
            ],
        ];

        $response = $this->postJson('/api/crm/events', $payload);

        $response->assertCreated();

        $event = CRMEvent::query()->where('title', 'Team Meeting')->first();
        expect($event->participants)->toHaveCount(2);
    });

    test('can create event with contact link', function (): void {
        $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

        $payload = [
            'title' => 'Client Call',
            'starts_at' => now()->addDay()->toIso8601String(),
            'type' => CRMEvent::TYPE_CALL,
            'links' => [
                ['type' => 'contact', 'id' => $contact->id],
            ],
        ];

        $response = $this->postJson('/api/crm/events', $payload);

        $response->assertCreated();

        $event = CRMEvent::query()->where('title', 'Client Call')->first();
        expect($event->links)->toHaveCount(1);
        expect($event->links->first()->linkable_id)->toBe($contact->id);
    });

    test('can create event with reminders', function (): void {
        $payload = [
            'title' => 'Important Meeting',
            'starts_at' => now()->addDay()->toIso8601String(),
            'type' => CRMEvent::TYPE_MEETING,
            'reminders' => [
                ['minutes_before' => 30, 'notify_email' => true],
                ['minutes_before' => 60, 'notify_push' => true],
            ],
        ];

        $response = $this->postJson('/api/crm/events', $payload);

        $response->assertCreated();

        $event = CRMEvent::query()->where('title', 'Important Meeting')->first();
        expect($event->reminders)->toHaveCount(2);
    });
});

describe('show', function (): void {
    test('can show event', function (): void {
        $event = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/crm/events/{$event->id}");

        $response->assertOk()
            ->assertJsonFragment(['title' => $event->title]);
    });

    test('cannot access other tenant event', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $otherEvent = CRMEvent::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson("/api/crm/events/{$otherEvent->id}");

        $response->assertNotFound();
    });
});

describe('update', function (): void {
    test('can update event', function (): void {
        $event = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
            'title' => 'Old Title',
        ]);

        $payload = [
            'title' => 'New Title',
            'starts_at' => now()->addDays(2)->toIso8601String(),
        ];

        $response = $this->putJson("/api/crm/events/{$event->id}", $payload);

        $response->assertOk()
            ->assertJsonFragment(['title' => 'New Title']);

        $this->assertDatabaseHas('crm_events', [
            'id' => $event->id,
            'title' => 'New Title',
        ]);
    });

    test('update replaces participants', function (): void {
        $event = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $event->participants()->create([
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
            'is_organizer' => true,
        ]);

        $otherUser = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);

        $payload = [
            'title' => $event->title,
            'starts_at' => $event->starts_at->toIso8601String(),
            'participants' => [
                ['auth_user_id' => $otherUser->id, 'is_organizer' => true],
            ],
        ];

        $response = $this->putJson("/api/crm/events/{$event->id}", $payload);

        $response->assertOk();

        $event->refresh();
        expect($event->participants)->toHaveCount(1);
        expect($event->participants->first()->auth_user_id)->toBe($otherUser->id);
    });
});

describe('destroy', function (): void {
    test('can delete event', function (): void {
        $event = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/crm/events/{$event->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('crm_events', ['id' => $event->id]);
    });
});

describe('updateStatus', function (): void {
    test('can update event status', function (): void {
        $event = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
            'status' => CRMEvent::STATUS_SCHEDULED,
        ]);

        $response = $this->patchJson("/api/crm/events/{$event->id}/status", [
            'status' => CRMEvent::STATUS_COMPLETED,
        ]);

        $response->assertOk();

        $event->refresh();
        expect($event->status)->toBe(CRMEvent::STATUS_COMPLETED);
    });

    test('rejects invalid status', function (): void {
        $event = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $response = $this->patchJson("/api/crm/events/{$event->id}/status", [
            'status' => 'invalid-status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    });
});

describe('calendar', function (): void {
    test('returns events for date range', function (): void {
        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
            'starts_at' => now()->addDays(2),
            'status' => CRMEvent::STATUS_SCHEDULED,
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
            'starts_at' => now()->addDays(10),
            'status' => CRMEvent::STATUS_SCHEDULED,
        ]);

        $response = $this->getJson('/api/crm/events/calendar?'.http_build_query([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    test('filters by user_id', function (): void {
        $otherUser = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);

        $event = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
            'starts_at' => now()->addDays(2),
            'status' => CRMEvent::STATUS_SCHEDULED,
        ]);

        $event->participants()->create([
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
            'is_organizer' => true,
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $otherUser->id,
            'starts_at' => now()->addDays(3),
            'status' => CRMEvent::STATUS_SCHEDULED,
        ]);

        $response = $this->getJson('/api/crm/events/calendar?'.http_build_query([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'user_id' => $this->user->id,
        ]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    test('requires start_date and end_date', function (): void {
        $response = $this->getJson('/api/crm/events/calendar');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date']);
    });
});

describe('upcoming', function (): void {
    test('returns upcoming events for user', function (): void {
        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
            'starts_at' => now()->addHours(3),
            'status' => CRMEvent::STATUS_SCHEDULED,
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
            'starts_at' => now()->subDay(),
            'status' => CRMEvent::STATUS_SCHEDULED,
        ]);

        $response = $this->getJson('/api/crm/events/upcoming');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    test('respects limit parameter', function (): void {
        CRMEvent::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
            'starts_at' => now()->addHours(3),
            'status' => CRMEvent::STATUS_SCHEDULED,
        ]);

        $response = $this->getJson('/api/crm/events/upcoming?limit=2');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });
});

describe('linked', function (): void {
    test('returns events linked to contact', function (): void {
        $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

        $event = CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auth_user_id' => $this->user->id,
        ]);

        $event->links()->create([
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
            'linkable_type' => CRMContact::class,
            'linkable_id' => $contact->id,
        ]);

        $response = $this->getJson("/api/crm/events/linked/contact/{$contact->id}");

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    test('returns empty for invalid type', function (): void {
        $response = $this->getJson('/api/crm/events/linked/invalid/some-id');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(0);
    });
});

describe('statistics', function (): void {
    test('returns statistics for events', function (): void {
        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => CRMEvent::STATUS_SCHEDULED,
            'type' => CRMEvent::TYPE_MEETING,
            'starts_at' => now()->addDay(),
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => CRMEvent::STATUS_COMPLETED,
            'type' => CRMEvent::TYPE_CALL,
            'starts_at' => now()->addDays(2),
        ]);

        $response = $this->getJson('/api/crm/events/statistics?'.http_build_query([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]));

        $response->assertOk();

        $data = $response->json('data');
        expect($data['total'])->toBe(2);
        expect($data['by_status'][CRMEvent::STATUS_SCHEDULED])->toBe(1);
        expect($data['by_status'][CRMEvent::STATUS_COMPLETED])->toBe(1);
        expect($data['by_type'][CRMEvent::TYPE_MEETING])->toBe(1);
        expect($data['by_type'][CRMEvent::TYPE_CALL])->toBe(1);
    });
});

describe('multi-tenant isolation', function (): void {
    test('listing only shows tenant events', function (): void {
        CRMEvent::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $otherTenant = PlatformTenant::factory()->create();
        CRMEvent::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson('/api/crm/events');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('cannot update other tenant event', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $otherEvent = CRMEvent::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->putJson("/api/crm/events/{$otherEvent->id}", [
            'title' => 'Hacked',
            'starts_at' => now()->toIso8601String(),
        ]);

        $response->assertNotFound();
    });

    test('cannot delete other tenant event', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $otherEvent = CRMEvent::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->deleteJson("/api/crm/events/{$otherEvent->id}");

        $response->assertNotFound();
    });
});
