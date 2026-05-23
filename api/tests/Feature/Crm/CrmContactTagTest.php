<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
});

test('can attach tag to contact', function (): void {
    $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);
    $tag = CRMTag::factory()->create(['tenant_id' => $this->tenant->id]);

    $payload = ['tag_id' => $tag->id];

    $response = $this->postJson("/api/crm/contacts/{$contact->id}/tags", $payload);

    $response->assertOk();

    $this->assertDatabaseHas('crm_contact_tags', [
        'crm_contact_id' => $contact->id,
        'crm_tag_id' => $tag->id,
    ]);
});

test('can detach tag from contact', function (): void {
    $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);
    $tag = CRMTag::factory()->create(['tenant_id' => $this->tenant->id]);

    $contact->tags()->attach($tag->id, [
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->deleteJson("/api/crm/contacts/{$contact->id}/tags/{$tag->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('crm_contact_tags', [
        'crm_contact_id' => $contact->id,
        'crm_tag_id' => $tag->id,
    ]);
});

test('listing contacts includes tags', function (): void {
    $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);
    $tag1 = CRMTag::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'VIP']);
    $tag2 = CRMTag::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Hot Lead']);

    $contact->tags()->attach([
        $tag1->id => [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
        ],
        $tag2->id => [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
        ],
    ]);

    $response = $this->getJson("/api/crm/contacts/{$contact->id}");

    $response->assertOk()
        ->assertJsonFragment(['name' => 'VIP'])
        ->assertJsonFragment(['name' => 'Hot Lead']);
});
