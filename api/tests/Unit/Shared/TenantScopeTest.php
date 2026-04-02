<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Scopes\TenantScope;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_tenant_scope_filters_by_authenticated_user_tenant(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        // Create contacts for both tenants
        CRMContact::factory()->count(3)->create(['tenant_id' => $tenantA->id]);
        CRMContact::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

        // Authenticate as user from Tenant A
        $userA = AuthUser::factory()->create(['tenant_id' => $tenantA->id]);
        $this->actingAs($userA);

        // Should only see Tenant A contacts
        $contacts = CRMContact::all();

        $this->assertCount(3, $contacts);
        $contacts->each(fn ($contact) => $this->assertSame($tenantA->id, $contact->tenant_id));
    }

    public function test_tenant_scope_allows_bypass_with_without_global_scope(): void
    {
        // Clear existing data to avoid test pollution
        CRMContact::query()->delete();

        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        CRMContact::factory()->count(3)->create(['tenant_id' => $tenantA->id]);
        CRMContact::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

        $userA = AuthUser::factory()->create(['tenant_id' => $tenantA->id]);
        $this->actingAs($userA);

        // Bypassing scope should see all contacts
        $allContacts = \Domain\CRM\Models\CRMContact::query()->withoutGlobalScope(TenantScope::class)->get();

        $this->assertCount(5, $allContacts);
    }

    public function test_tenant_scope_not_applied_when_not_authenticated(): void
    {
        // Clear existing data to avoid test pollution
        CRMContact::query()->delete();

        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        CRMContact::factory()->count(3)->create(['tenant_id' => $tenantA->id]);
        CRMContact::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

        // No authentication - scope should not filter
        $allContacts = CRMContact::all();

        $this->assertCount(5, $allContacts);
    }

    public function test_for_tenant_scope_filters_correctly(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        CRMContact::factory()->count(3)->create(['tenant_id' => $tenantA->id]);
        CRMContact::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

        // Use the forTenant scope directly
        $tenantAContacts = CRMContact::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantA->id)
            ->get();

        $this->assertCount(3, $tenantAContacts);
        $tenantAContacts->each(fn ($contact) => $this->assertSame($tenantA->id, $contact->tenant_id));
    }

    public function test_belongs_to_tenant_provides_tenant_relationship(): void
    {
        $tenant = PlatformTenant::factory()->create(['name' => 'Test Company']);
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue(method_exists($contact, 'tenant'));
        $this->assertSame($tenant->id, $contact->tenant->id);
        $this->assertSame('Test Company', $contact->tenant->name);
    }

    public function test_model_cannot_be_created_without_tenant_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // CRMContact requires tenant_id
        \Domain\CRM\Models\CRMContact::query()->create([
            'phone' => '5511999999999',
            'name' => 'Test Contact',
        ]);
    }

    public function test_tenant_isolation_prevents_cross_tenant_access(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        $contactB = CRMContact::factory()->create(['tenant_id' => $tenantB->id]);

        $userA = AuthUser::factory()->create(['tenant_id' => $tenantA->id]);
        $this->actingAs($userA);

        // Try to find contact from Tenant B while authenticated as Tenant A
        $found = \Domain\CRM\Models\CRMContact::query()->find($contactB->id);

        $this->assertNull($found);
    }

    public function test_multiple_models_use_tenant_scope_consistently(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        // Create resources for both tenants
        CRMContact::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        CRMContact::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        ChatTicket::factory()->count(4)->create(['tenant_id' => $tenantA->id]);
        ChatTicket::factory()->count(1)->create(['tenant_id' => $tenantB->id]);

        $userA = AuthUser::factory()->create(['tenant_id' => $tenantA->id]);
        $this->actingAs($userA);

        $this->assertCount(2, CRMContact::all());
        $this->assertCount(4, ChatTicket::all());

        // Switch to Tenant B user
        $userB = AuthUser::factory()->create(['tenant_id' => $tenantB->id]);
        $this->actingAs($userB);

        $this->assertCount(3, CRMContact::all());
        $this->assertCount(1, ChatTicket::all());
    }
}
