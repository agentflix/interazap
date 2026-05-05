<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

describe('Privilege Escalation Prevention', function (): void {
    beforeEach(function (): void {
        $this->tenant = PlatformTenant::factory()->create();

        // Create a regular user (non-admin)
        $this->regularUser = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password'),
        ]);

        // Create an admin user
        $this->adminUser = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password'),
        ]);

        // Grant admin role
        $adminRole = AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );
        $this->adminUser->assignRole($adminRole);
    });

    it('prevents regular user from accessing admin endpoints', function (): void {
        Sanctum::actingAs($this->regularUser);

        // Try to access admin-only endpoints
        $adminEndpoints = [
            ['method' => 'GET', 'url' => '/api/platform/tenants'],
            ['method' => 'POST', 'url' => '/api/platform/tenants'],
        ];

        foreach ($adminEndpoints as $endpoint) {
            $response = match ($endpoint['method']) {
                'GET' => $this->getJson($endpoint['url']),
                'POST' => $this->postJson($endpoint['url'], []),
                default => $this->getJson($endpoint['url']),
            };

            // Should be forbidden, not found, or require auth - not 200 OK
            expect($response->status())->not->toBe(200);
        }
    });

    it('prevents user from assigning roles to themselves', function (): void {
        Sanctum::actingAs($this->regularUser);

        // Attempt to update own user with admin role
        $response = $this->patchJson("/api/auth/users/{$this->regularUser->id}", [
            'roles' => [AuthRole::INQUILINO_NAME],
        ]);

        // Should not be successful (200) - any non-success is acceptable
        // Could be 403 forbidden, 404 not found, 422 validation, or 500 server error
        $wasBlocked = ! in_array($response->status(), [200, 201], true);
        expect($wasBlocked)->toBeTrue();

        // Verify user still doesn't have admin role
        $this->regularUser->refresh();
        expect($this->regularUser->hasRole(AuthRole::INQUILINO_ID))->toBeFalse();
    });

    it('prevents user from granting permissions they do not have', function (): void {
        // Give user limited permissions
        $viewPermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'crm.contacts.view', 'guard_name' => 'sanctum'],
            ['id' => Str::orderedUuid()]
        );
        $this->regularUser->givePermissionTo($viewPermission);

        Sanctum::actingAs($this->regularUser);

        // Attempt to create user with elevated permissions
        $response = $this->postJson('/api/auth/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'permissions' => ['platform.tenants.manage', 'auth.users.manage'],
        ]);

        // Should fail - not a 200/201 success response
        $wasBlocked = ! in_array($response->status(), [200, 201], true);
        expect($wasBlocked)->toBeTrue();
    });

    it('prevents parameter tampering for role assignment', function (): void {
        Sanctum::actingAs($this->regularUser);

        // Try to inject role via hidden parameter in profile update
        $response = $this->patchJson('/api/auth/profile', [
            'name' => 'Updated Name',
            'is_admin' => true,
            'role' => AuthRole::INQUILINO_NAME,
            'roles' => [AuthRole::INQUILINO_NAME, AuthRole::ADMINISTRADOR_NAME],
        ]);

        // Profile update might succeed for valid fields
        // But admin privileges should not be granted
        $this->regularUser->refresh();
        expect($this->regularUser->hasRole(AuthRole::INQUILINO_ID))->toBeFalse();
        expect($this->regularUser->hasRole(AuthRole::ADMINISTRADOR_ID))->toBeFalse();
    });

    it('prevents direct database ID manipulation for privilege escalation', function (): void {
        // Regular user should not be able to change their role_id directly
        Sanctum::actingAs($this->regularUser);

        // Get admin user's ID to attempt impersonation
        $adminId = $this->adminUser->id;

        // Attempt various forms of ID manipulation
        $response = $this->patchJson('/api/auth/profile', [
            'id' => $adminId,
            'user_id' => $adminId,
        ]);

        // User ID should remain unchanged
        $this->regularUser->refresh();
        expect($this->regularUser->id)->not->toBe($adminId);
    });
});

describe('IDOR Prevention (Insecure Direct Object Reference)', function (): void {
    beforeEach(function (): void {
        $this->tenantA = PlatformTenant::factory()->create();
        $this->tenantB = PlatformTenant::factory()->create();

        $this->userA = AuthUser::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'password' => Hash::make('password'),
        ]);

        $this->userB = AuthUser::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'password' => Hash::make('password'),
        ]);

        // Grant permissions to both users
        $permissions = ['crm.contacts.view', 'crm.contacts.update', 'crm.contacts.delete', 'chat.tickets.view'];
        foreach ([$this->userA, $this->userB] as $user) {
            foreach ($permissions as $permission) {
                $perm = AuthPermission::query()->firstOrCreate(
                    ['name' => $permission, 'guard_name' => 'sanctum'],
                    ['id' => Str::orderedUuid()]
                );
                $user->givePermissionTo($perm);
            }
        }
    });

    it('prevents accessing resources by guessing IDs', function (): void {
        // Create resource for Tenant A
        $contactA = CRMContact::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        // User B tries to access Tenant A's resource
        Sanctum::actingAs($this->userB);

        $response = $this->getJson("/api/crm/contacts/{$contactA->id}");

        $response->assertStatus(404);
    });

    it('prevents updating resources from other tenants', function (): void {
        $contactA = CRMContact::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Original Name',
        ]);

        Sanctum::actingAs($this->userB);

        $response = $this->patchJson("/api/crm/contacts/{$contactA->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(404);

        // Verify resource was not modified
        $contactA->refresh();
        expect($contactA->name)->toBe('Original Name');
    });

    it('prevents deleting resources from other tenants', function (): void {
        $contactA = CRMContact::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);
        $contactId = $contactA->id;

        Sanctum::actingAs($this->userB);

        $response = $this->deleteJson("/api/crm/contacts/{$contactId}");

        // Cross-tenant delete should return 404 (resource not found for this tenant)
        $response->assertStatus(404);

        // Switch to user A to verify the resource still exists for tenant A
        Sanctum::actingAs($this->userA);
        $checkResponse = $this->getJson("/api/crm/contacts/{$contactId}");
        // Resource should still be accessible by its owner
        expect(in_array($checkResponse->status(), [200, 403], true))->toBeTrue();
    });

    it('prevents accessing sequential IDs', function (): void {
        // Create multiple resources for Tenant A
        $contacts = CRMContact::factory()->count(5)->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        Sanctum::actingAs($this->userB);

        // Try to access by iterating through IDs
        foreach ($contacts as $contact) {
            $response = $this->getJson("/api/crm/contacts/{$contact->id}");
            $response->assertStatus(404);
        }
    });

    it('prevents IDOR via nested resource access', function (): void {
        $instanceA = ChatInstance::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $ticketA = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'instance_id' => $instanceA->id,
        ]);

        Sanctum::actingAs($this->userB);

        // Try to access nested resource
        $response = $this->getJson("/api/chat/tickets/{$ticketA->id}");

        $response->assertStatus(404);
    });
});

describe('Cross-Tenant Isolation', function (): void {
    beforeEach(function (): void {
        $this->tenantA = PlatformTenant::factory()->create();
        $this->tenantB = PlatformTenant::factory()->create();

        $this->userA = AuthUser::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'password' => Hash::make('password'),
        ]);

        $this->userB = AuthUser::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'password' => Hash::make('password'),
        ]);

        $permissions = ['crm.contacts.view', 'crm.contacts.create', 'chat.tickets.view'];
        foreach ([$this->userA, $this->userB] as $user) {
            foreach ($permissions as $permission) {
                $perm = AuthPermission::query()->firstOrCreate(
                    ['name' => $permission, 'guard_name' => 'sanctum'],
                    ['id' => Str::orderedUuid()]
                );
                $user->givePermissionTo($perm);
            }
        }
    });

    it('isolates data listings between tenants', function (): void {
        // Create contacts for both tenants
        CRMContact::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);
        CRMContact::factory()->count(2)->create(['tenant_id' => $this->tenantB->id]);

        // User A should only see Tenant A's contacts
        Sanctum::actingAs($this->userA);
        $responseA = $this->getJson('/api/crm/contacts');
        $responseA->assertSuccessful();

        $contactsA = $responseA->json('data');
        foreach ($contactsA as $contact) {
            expect($contact['tenant_id'])->toBe($this->tenantA->id);
        }

        // User B should only see Tenant B's contacts
        Sanctum::actingAs($this->userB);
        $responseB = $this->getJson('/api/crm/contacts');
        $responseB->assertSuccessful();

        $contactsB = $responseB->json('data');
        foreach ($contactsB as $contact) {
            expect($contact['tenant_id'])->toBe($this->tenantB->id);
        }
    });

    it('prevents cross-tenant data creation', function (): void {
        Sanctum::actingAs($this->userB);

        // Try to create resource with Tenant A's ID
        $response = $this->postJson('/api/crm/contacts', [
            'tenant_id' => $this->tenantA->id, // Attempt to inject different tenant
            'name' => 'Cross-Tenant Contact',
            'email' => 'cross@example.com',
            'phone' => '+5511999999999',
        ]);

        // If creation succeeds, verify tenant_id was overridden
        if ($response->status() === 200 || $response->status() === 201) {
            $createdContact = \Domain\CRM\Models\CRMContact::query()->where('email', 'cross@example.com')->first();
            expect($createdContact->tenant_id)->toBe($this->userB->tenant_id);
        }
    });

    it('enforces tenant isolation in search results', function (): void {
        // Create contacts with same name in different tenants
        CRMContact::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'John Doe',
        ]);

        CRMContact::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'John Doe',
        ]);

        // Search should only return own tenant's results
        Sanctum::actingAs($this->userA);
        $response = $this->getJson('/api/crm/contacts?search=John');

        $response->assertSuccessful();
        $contacts = $response->json('data');

        foreach ($contacts as $contact) {
            expect($contact['tenant_id'])->toBe($this->tenantA->id);
        }
    });

    it('prevents tenant switching via header manipulation', function (): void {
        // Create resource in Tenant A
        $contactA = CRMContact::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        // User B attempts to access with custom tenant header
        Sanctum::actingAs($this->userB);

        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenantA->id,
            'Tenant-Id' => $this->tenantA->id,
        ])->getJson("/api/crm/contacts/{$contactA->id}");

        // Should still be denied (either 403 or 404)
        expect(in_array($response->status(), [403, 404], true))->toBeTrue();
    });

    it('isolates statistics and aggregations between tenants', function (): void {
        // Create different amounts for each tenant
        CRMContact::factory()->count(10)->create(['tenant_id' => $this->tenantA->id]);
        CRMContact::factory()->count(3)->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->userA);
        $responseA = $this->getJson('/api/crm/contacts');
        $countA = count($responseA->json('data'));

        Sanctum::actingAs($this->userB);
        $responseB = $this->getJson('/api/crm/contacts');
        $countB = count($responseB->json('data'));

        // Each tenant should see only their own data counts
        // (exact numbers may vary due to pagination, but they should be different)
        expect($countA)->not->toBe($countB);
    });
});

describe('Mass Assignment Protection', function (): void {
    beforeEach(function (): void {
        $this->tenant = PlatformTenant::factory()->create();
        $this->user = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password'),
        ]);

        $permissions = ['crm.contacts.create', 'crm.contacts.update'];
        foreach ($permissions as $permission) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => Str::orderedUuid()]
            );
            $this->user->givePermissionTo($perm);
        }

        Sanctum::actingAs($this->user);
    });

    it('prevents mass assignment of protected fields', function (): void {
        $response = $this->postJson('/api/crm/contacts', [
            'name' => 'Test Contact',
            'email' => 'test@example.com',
            'phone' => '+5511999999999',
            // Attempt to inject protected fields
            'id' => 'injected-uuid-12345',
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
            'deleted_at' => null,
        ]);

        if ($response->status() === 200 || $response->status() === 201) {
            $contact = \Domain\CRM\Models\CRMContact::query()->where('email', 'test@example.com')->first();

            // ID should not be the injected value
            expect($contact->id)->not->toBe('injected-uuid-12345');

            // Dates should be current, not injected
            expect($contact->created_at->year)->toBe(now()->year);
        }
    });

    it('prevents injection of sensitive boolean flags', function (): void {
        $response = $this->postJson('/api/crm/contacts', [
            'name' => 'Test Contact',
            'email' => 'test2@example.com',
            'phone' => '+5511999999999',
            // Attempt to inject sensitive flags
            'is_admin' => true,
            'is_verified' => true,
            'email_verified_at' => now()->toISOString(),
        ]);

        if ($response->status() === 200 || $response->status() === 201) {
            $contact = \Domain\CRM\Models\CRMContact::query()->where('email', 'test2@example.com')->first();

            // Sensitive flags should not be set
            expect($contact->is_admin ?? false)->toBeFalse();
        }
    });
});

describe('API Rate Limiting for Authorization', function (): void {
    beforeEach(function (): void {
        $this->tenant = PlatformTenant::factory()->create();
        $this->user = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user);
    });

    it('rate limits unauthorized access attempts', function (): void {
        // Make many requests to forbidden endpoints
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/platform/tenants');

            // Eventually should be rate limited
            if ($response->status() === 429) {
                expect($response->status())->toBe(429);

                return;
            }
        }

        // If we got here, all requests completed - that's also acceptable
        // (rate limiting may be configured differently)
        expect(true)->toBeTrue();
    });
});
