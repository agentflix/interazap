<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class GlobalSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformTenant $tenant;

    private AuthUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->tenant = PlatformTenant::factory()->create();
        $this->user = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_search_returns_contacts_companies_and_negotiations_from_current_tenant(): void
    {
        $this->grantPermissions($this->user, [
            'crm.contacts.view',
            'crm.companies.view',
            'crm.negotiations.view',
        ]);

        $company = CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Joao Company',
        ]);

        CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_company_id' => $company->id,
            'name' => 'Joao Contact',
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Negociacao Joao',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/search?q=joao')
            ->assertOk()
            ->assertJsonPath('data.contacts.total', 1)
            ->assertJsonPath('data.companies.total', 1)
            ->assertJsonPath('data.negotiations.total', 1);
    }

    public function test_search_respects_tenant_isolation(): void
    {
        $this->grantPermissions($this->user, ['crm.contacts.view']);

        $otherTenant = PlatformTenant::factory()->create();

        $allowed = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Isolated Result',
        ]);

        CRMContact::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Isolated Result',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/search?q=isolated')
            ->assertOk();

        $response->assertJsonPath('data.contacts.total', 1);
        $response->assertJsonPath('data.contacts.items.0.id', $allowed->id);
    }

    public function test_search_filters_only_requested_types(): void
    {
        $this->grantPermissions($this->user, ['crm.contacts.view', 'crm.companies.view']);

        CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Filtro Joao',
        ]);

        CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Filtro Joao',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/search?q=filtro&types[]=contacts')
            ->assertOk();

        $response->assertJsonPath('data.contacts.total', 1);
        $response->assertJsonMissingPath('data.companies');
    }

    public function test_search_respects_per_type_limit(): void
    {
        $this->grantPermissions($this->user, ['crm.contacts.view']);

        CRMContact::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Limited Contact',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/search?q=limited&per_type=2')
            ->assertOk();

        $response->assertJsonPath('data.contacts.total', 3);
        $response->assertJsonCount(2, 'data.contacts.items');
    }

    public function test_search_with_short_query_returns_422(): void
    {
        $this->grantPermissions($this->user, ['crm.contacts.view']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/search?q=a')
            ->assertStatus(422);
    }

    public function test_search_without_results_returns_empty_groups(): void
    {
        $this->grantPermissions($this->user, [
            'crm.contacts.view',
            'crm.companies.view',
            'crm.negotiations.view',
            'chat.tickets.view',
            'auth.users.view',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/search?q=semresultados')
            ->assertOk()
            ->assertJsonPath('data.contacts.total', 0)
            ->assertJsonPath('data.companies.total', 0)
            ->assertJsonPath('data.negotiations.total', 0)
            ->assertJsonPath('data.tickets.total', 0)
            ->assertJsonPath('data.users.total', 0)
            ->assertJsonPath('data.contacts.items', []);
    }

    public function test_user_without_contacts_permission_does_not_receive_contacts_group(): void
    {
        $this->grantPermissions($this->user, ['crm.companies.view']);

        CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Restricted Contact',
        ]);

        CRMCompany::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Restricted Contact',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/search?q=restricted')
            ->assertOk();

        $response->assertJsonMissingPath('data.contacts');
        $response->assertJsonPath('data.companies.total', 1);
    }

    public function test_user_without_users_permission_does_not_receive_users_group(): void
    {
        $this->grantPermissions($this->user, ['crm.contacts.view']);

        AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Search User',
            'email' => 'search.user@example.test',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/search?q=search')
            ->assertOk();

        $response->assertJsonMissingPath('data.users');
    }

    public function test_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/search?q=test')
            ->assertUnauthorized();
    }

    public function test_response_includes_meta_fields(): void
    {
        $this->grantPermissions($this->user, ['crm.contacts.view']);

        CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Meta Contact',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/search?q=meta&per_type=7')
            ->assertOk()
            ->assertJsonPath('meta.query', 'meta')
            ->assertJsonPath('meta.per_type', 7)
            ->assertJsonStructure([
                'meta' => ['query', 'total', 'per_type', 'duration_ms'],
            ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grantPermissions(AuthUser $user, array $permissions): void
    {
        foreach ($permissions as $permissionName) {
            $permission = AuthPermission::query()->firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::orderedUuid()]
            );

            $user->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
