<?php

declare(strict_types=1);

use Database\Seeders\AuthPermissionSeeder;
use Domain\Auth\Models\AuthUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AuthPermissionSeeder::class);
    $this->user = AuthUser::factory()->create();
    $this->user->givePermissionTo('reports.crm.view');
    Sanctum::actingAs($this->user);
});

it('returns contact summary totals', function (): void {
    $tenantId = $this->user->tenant_id;

    DB::table('crm_contacts')->insert([
        ['id' => Str::uuid(), 'tenant_id' => $tenantId, 'name' => 'Active 1', 'is_active' => true, 'created_at' => now()->subDays(5), 'updated_at' => now()],
        ['id' => Str::uuid(), 'tenant_id' => $tenantId, 'name' => 'Active 2', 'is_active' => true, 'created_at' => now()->subDays(3), 'updated_at' => now()],
        ['id' => Str::uuid(), 'tenant_id' => $tenantId, 'name' => 'Inactive', 'is_active' => false, 'created_at' => now()->subDays(10), 'updated_at' => now()],
    ]);

    $response = $this->getJson('/api/reports/contact-crm?'.http_build_query([
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertOk();
    $data = $response->json('data.data');

    expect($data)->toHaveKeys(['summary', 'by_company', 'cold_leads', 'monthly_growth', 'top_tags']);
    expect($data['summary']['total'])->toBe(3);
    expect($data['summary']['active'])->toBe(2);
    expect($data['summary']['inactive'])->toBe(1);
    expect($data['summary']['new_in_period'])->toBeGreaterThanOrEqual(3);
});

it('returns contacts by company', function (): void {
    $tenantId = $this->user->tenant_id;
    $companyId = Str::uuid()->toString();

    DB::table('crm_companies')->insert([
        'id' => $companyId,
        'tenant_id' => $tenantId,
        'name' => 'ACME Corp',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('crm_contacts')->insert([
        ['id' => Str::uuid(), 'tenant_id' => $tenantId, 'crm_company_id' => $companyId, 'name' => 'Contact A', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => Str::uuid(), 'tenant_id' => $tenantId, 'crm_company_id' => $companyId, 'name' => 'Contact B', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $response = $this->getJson('/api/reports/contact-crm?'.http_build_query([
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertOk();

    $byCompany = $response->json('data.data.by_company');
    expect($byCompany)->not->toBeEmpty();
    expect($byCompany[0]['company_name'])->toBe('ACME Corp');
    expect($byCompany[0]['contact_count'])->toBe(2);
});

it('returns cold leads counts', function (): void {
    $tenantId = $this->user->tenant_id;

    DB::table('crm_contacts')->insert([
        'id' => Str::uuid(),
        'tenant_id' => $tenantId,
        'name' => 'Cold Lead',
        'is_active' => true,
        'created_at' => now()->subDays(60),
        'updated_at' => now(),
    ]);

    $response = $this->getJson('/api/reports/contact-crm?'.http_build_query([
        'start_date' => now()->subDays(90)->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertOk();

    $coldLeads = $response->json('data.data.cold_leads');
    expect($coldLeads)->toHaveKeys(['no_negotiation', 'no_chat_30_days']);
    expect($coldLeads['no_negotiation'])->toBeGreaterThanOrEqual(1);
});

it('returns monthly growth data', function (): void {
    $tenantId = $this->user->tenant_id;

    DB::table('crm_contacts')->insert([
        ['id' => Str::uuid(), 'tenant_id' => $tenantId, 'name' => 'Jan Contact', 'is_active' => true, 'created_at' => now()->subDays(5), 'updated_at' => now()],
        ['id' => Str::uuid(), 'tenant_id' => $tenantId, 'name' => 'Jan Contact 2', 'is_active' => true, 'created_at' => now()->subDays(3), 'updated_at' => now()],
    ]);

    $response = $this->getJson('/api/reports/contact-crm?'.http_build_query([
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertOk();
    expect($response->json('data.data.monthly_growth'))->not->toBeEmpty();
});

it('enforces tenant isolation', function (): void {
    $otherUser = AuthUser::factory()->create();

    DB::table('crm_contacts')->insert([
        'id' => Str::uuid(),
        'tenant_id' => $otherUser->tenant_id,
        'name' => 'Other Tenant Contact',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->getJson('/api/reports/contact-crm?'.http_build_query([
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertOk();
    expect($response->json('data.data.summary.total'))->toBe(0);
});

it('denies access without permission', function (): void {
    $userNoPerms = AuthUser::factory()->create();
    Sanctum::actingAs($userNoPerms);

    $response = $this->getJson('/api/reports/contact-crm?'.http_build_query([
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $response->assertForbidden();
});
