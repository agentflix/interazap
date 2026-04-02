<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
});

test('can create tag', function (): void {
    $response = $this->postJson('/api/crm/tags', ['name' => 'VIP']);

    $response->assertCreated()->assertJsonFragment(['name' => 'VIP']);

    $this->assertDatabaseHas('crm_tags', [
        'tenant_id' => $this->tenant->id,
        'name' => 'VIP',
    ]);
});

test('can list tags', function (): void {
    CRMTag::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/crm/tags');

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('can delete tag', function (): void {
    $tag = CRMTag::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->deleteJson("/api/crm/tags/{$tag->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('crm_tags', ['id' => $tag->id]);
});

test('can retrieve all active tags', function (): void {
    CRMTag::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
    CRMTag::factory()->count(1)->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

    $response = $this->getJson('/api/crm/tags/all');

    $response->assertOk()
        ->assertJsonCount(2, 'data.tags');
});

test('can retrieve single tag', function (): void {
    $tag = CRMTag::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Specific']);

    $response = $this->getJson("/api/crm/tags/{$tag->id}");

    $response->assertOk()
        ->assertJsonPath('data.name', 'Specific');
});
