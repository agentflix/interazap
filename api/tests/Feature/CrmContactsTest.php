<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContactPhone;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMContactsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cria_contato_com_phone_e_lista_por_tenant(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        Sanctum::actingAs($user, abilities: ['*']);

        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone_e164' => '+5511987654321',
        ];

        $create = $this->postJson('/api/crm/contacts', $payload)
            ->assertStatus(201)
            ->json('data');

        $this->assertSame($payload['name'], $create['name']);

        $list = $this->getJson('/api/crm/contacts')->assertStatus(200);
        $this->assertCount(1, $list->json('data'));

        $phone = CRMContactPhone::query()->first();
        $this->assertSame('+5511987654321', $phone->phone_e164);
        $this->assertEquals($user->tenant_id, $phone->tenant_id);
    }

    public function test_phone_e164_e_unico_por_tenant(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        Sanctum::actingAs($user, abilities: ['*']);

        $payload = [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'phone_e164' => '+5511900000001',
        ];

        $this->postJson('/api/crm/contacts', $payload)->assertStatus(201);

        $this->postJson('/api/crm/contacts', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'phone_e164' => '+5511900000001',
        ])->assertStatus(422);
    }

    public function test_contacts_are_isolated_by_tenant(): void
    {
        $tenantAUser = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);
        $tenantBUser = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        Sanctum::actingAs($tenantAUser, abilities: ['*']);
        $contactA = $this->postJson('/api/crm/contacts', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'phone_e164' => '+551190000001',
        ])->assertStatus(201)->json('data');

        Sanctum::actingAs($tenantBUser, abilities: ['*']);
        $contactB = $this->postJson('/api/crm/contacts', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'phone_e164' => '+551190000002',
        ])->assertStatus(201)->json('data');

        // Tenant B não enxerga contatos do tenant A
        $this->getJson('/api/crm/contacts')->assertJsonMissing(['id' => $contactA['id']]);
        $this->getJson('/api/crm/contacts/'.$contactA['id'])->assertStatus(404);

        // Tenant A não enxerga contatos do tenant B
        Sanctum::actingAs($tenantAUser, abilities: ['*']);
        $this->getJson('/api/crm/contacts')->assertJsonMissing(['id' => $contactB['id']]);
        $this->getJson('/api/crm/contacts/'.$contactB['id'])->assertStatus(404);
    }

    public function test_patch_contact_updates_partial_fields(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        Sanctum::actingAs($user, abilities: ['*']);

        $contact = $this->postJson('/api/crm/contacts', [
            'name' => 'Maria',
            'email' => 'maria@interazap.test',
            'phone_e164' => '+5511900000003',
        ])->assertStatus(201)->json('data');

        $patch = $this->patchJson('/api/crm/contacts/'.$contact['id'], [
            'email' => 'maria.nova@interazap.test',
            'document' => '12345678901',
        ])->assertStatus(200)->json('data');

        $this->assertSame('maria.nova@interazap.test', $patch['email']);
        $this->assertSame('12345678901', $patch['document']);
    }
}
