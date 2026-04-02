<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMEvent;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMProposal;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Multi-tenancy isolation tests for CRM domain.
 *
 * Validates that tenants cannot access each other's:
 * - Contacts
 * - Negotiations
 * - Proposals
 * - Events
 */
class CRMMultiTenancyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $userA;

    private AuthUser $userB;

    private PlatformTenant $tenantA;

    private PlatformTenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = PlatformTenant::factory()->create();
        $this->tenantB = PlatformTenant::factory()->create();

        $this->userA = AuthUser::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->userB = AuthUser::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);

        $permissions = [
            'crm.contacts.view',
            'crm.contacts.create',
            'crm.contacts.update',
            'crm.contacts.delete',
            'crm.negotiations.view',
            'crm.negotiations.create',
            'crm.negotiations.update',
            'crm.negotiations.delete',
            'crm.proposals.view',
            'crm.proposals.create',
            'crm.proposals.update',
            'crm.proposals.delete',
            'crm.events.view',
            'crm.events.create',
            'crm.events.update',
            'crm.events.delete',
        ];

        foreach ($permissions as $permission) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::orderedUuid()]
            );
            $this->userA->givePermissionTo($perm);
            $this->userB->givePermissionTo($perm);
        }
    }

    public function test_user_cannot_see_other_tenant_contacts(): void
    {
        CRMContact::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Contact A',
        ]);

        CRMContact::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Contact B',
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/crm/contacts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Contact A');

        $this->actingAs($this->userB, 'sanctum')
            ->getJson('/api/crm/contacts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Contact B');
    }

    public function test_user_cannot_access_other_tenant_contact_by_id(): void
    {
        $contactB = CRMContact::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/crm/contacts/{$contactB->id}")
            ->assertNotFound();
    }

    public function test_user_cannot_update_other_tenant_contact(): void
    {
        $contactB = CRMContact::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Original Name',
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->putJson("/api/crm/contacts/{$contactB->id}", [
                'name' => 'Hacked Name',
            ])
            ->assertNotFound();

        $contactB->refresh();
        $this->assertEquals('Original Name', $contactB->name);
    }

    public function test_user_cannot_delete_other_tenant_contact(): void
    {
        $contactB = CRMContact::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->deleteJson("/api/crm/contacts/{$contactB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('crm_contacts', [
            'id' => $contactB->id,
        ]);
    }

    public function test_user_cannot_see_other_tenant_negotiations(): void
    {
        $funnelA = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantA->id]);
        $stepA = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'crm_negotiation_funnel_id' => $funnelA->id,
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'title' => 'Deal A',
            'crm_negotiation_funnel_id' => $funnelA->id,
            'crm_negotiation_funnel_step_id' => $stepA->id,
        ]);

        $funnelB = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantB->id]);
        $stepB = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'crm_negotiation_funnel_id' => $funnelB->id,
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'title' => 'Deal B',
            'crm_negotiation_funnel_id' => $funnelB->id,
            'crm_negotiation_funnel_step_id' => $stepB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/crm/negotiations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Deal A');
    }

    public function test_user_cannot_access_other_tenant_negotiation_by_id(): void
    {
        $funnelB = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantB->id]);
        $stepB = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'crm_negotiation_funnel_id' => $funnelB->id,
        ]);

        $negotiationB = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'crm_negotiation_funnel_id' => $funnelB->id,
            'crm_negotiation_funnel_step_id' => $stepB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/crm/negotiations/{$negotiationB->id}")
            ->assertNotFound();
    }

    public function test_user_cannot_update_other_tenant_negotiation(): void
    {
        $funnelB = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantB->id]);
        $stepB = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'crm_negotiation_funnel_id' => $funnelB->id,
        ]);

        $negotiationB = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'title' => 'Original Deal',
            'amount' => 1000,
            'crm_negotiation_funnel_id' => $funnelB->id,
            'crm_negotiation_funnel_step_id' => $stepB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->putJson("/api/crm/negotiations/{$negotiationB->id}", [
                'title' => 'Hacked Deal',
                'amount' => 2000,
                'crm_negotiation_funnel_id' => $funnelB->id,
                'crm_negotiation_funnel_step_id' => $stepB->id,
            ])
            ->assertStatus(422);

        $negotiationB->refresh();
        $this->assertEquals('Original Deal', $negotiationB->title);
    }

    public function test_user_cannot_mark_other_tenant_negotiation_as_won(): void
    {
        $funnelB = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantB->id]);
        $stepB = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'crm_negotiation_funnel_id' => $funnelB->id,
        ]);

        $negotiationB = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'status' => 'open',
            'crm_negotiation_funnel_id' => $funnelB->id,
            'crm_negotiation_funnel_step_id' => $stepB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/crm/negotiations/{$negotiationB->id}/won")
            ->assertNotFound();

        $negotiationB->refresh();
        $this->assertEquals('open', $negotiationB->status->value);
    }

    public function test_user_cannot_access_other_tenant_proposal_by_id(): void
    {
        $funnelB = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantB->id]);
        $stepB = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'crm_negotiation_funnel_id' => $funnelB->id,
        ]);

        $negotiationB = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'crm_negotiation_funnel_id' => $funnelB->id,
            'crm_negotiation_funnel_step_id' => $stepB->id,
        ]);

        $proposalB = CRMProposal::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'crm_negotiation_id' => $negotiationB->id,
            'title' => 'Proposal B',
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/crm/proposals/{$proposalB->id}")
            ->assertNotFound();
    }

    public function test_user_cannot_list_other_tenant_negotiation_proposals(): void
    {
        $funnelB = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantB->id]);
        $stepB = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'crm_negotiation_funnel_id' => $funnelB->id,
        ]);

        $negotiationB = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'crm_negotiation_funnel_id' => $funnelB->id,
            'crm_negotiation_funnel_step_id' => $stepB->id,
        ]);

        CRMProposal::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'crm_negotiation_id' => $negotiationB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/crm/negotiations/{$negotiationB->id}/proposals")
            ->assertNotFound();
    }

    public function test_user_cannot_see_other_tenant_events(): void
    {
        CRMEvent::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'auth_user_id' => $this->userA->id,
            'title' => 'Event A',
        ]);

        CRMEvent::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'auth_user_id' => $this->userB->id,
            'title' => 'Event B',
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/crm/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Event A');
    }

    public function test_user_cannot_access_other_tenant_event_by_id(): void
    {
        $eventB = CRMEvent::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'auth_user_id' => $this->userB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/crm/events/{$eventB->id}")
            ->assertNotFound();
    }

    public function test_contact_creation_enforces_tenant_isolation(): void
    {
        $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/crm/contacts', [
                'name' => 'New Contact',
                'email' => 'new@example.com',
            ])
            ->assertCreated();

        $contact = CRMContact::query()->where('name', 'New Contact')->first();
        $this->assertEquals((string) $this->tenantA->id, $contact->tenant_id);
    }

    public function test_event_creation_enforces_tenant_isolation(): void
    {
        $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/crm/events', [
                'title' => 'New Event',
                'starts_at' => now()->addDay()->toIso8601String(),
            ])
            ->assertCreated();

        $event = CRMEvent::query()->where('title', 'New Event')->first();
        $this->assertEquals((string) $this->tenantA->id, $event->tenant_id);
    }

    public function test_negotiation_counts_only_include_own_tenant(): void
    {
        $funnelA = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantA->id]);
        $stepA = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'crm_negotiation_funnel_id' => $funnelA->id,
        ]);

        CRMNegotiation::factory()->count(3)->create([
            'tenant_id' => $this->tenantA->id,
            'status' => 'open',
            'crm_negotiation_funnel_id' => $funnelA->id,
            'crm_negotiation_funnel_step_id' => $stepA->id,
        ]);

        $funnelB = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantB->id]);
        $stepB = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'crm_negotiation_funnel_id' => $funnelB->id,
        ]);

        CRMNegotiation::factory()->count(5)->create([
            'tenant_id' => $this->tenantB->id,
            'status' => 'open',
            'crm_negotiation_funnel_id' => $funnelB->id,
            'crm_negotiation_funnel_step_id' => $stepB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/crm/negotiations')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->actingAs($this->userB, 'sanctum')
            ->getJson('/api/crm/negotiations')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }
}
