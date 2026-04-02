<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTransmissionList;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatTransmissionListTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = AuthUser::factory()->create();

        // Setup permissions
        $permissions = [
            'chat.transmission_lists.view',
            'chat.transmission_lists.create',
            'chat.transmission_lists.update',
            'chat.transmission_lists.delete',
        ];

        foreach ($permissions as $perm) {
            \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }

        $this->user->givePermissionTo($permissions);

        $this->actingAs($this->user);
    }

    public function test_can_create_transmission_list(): void
    {
        $payload = [
            'name' => 'Lista de Teste',
            'message' => 'Olá {{name}}',
            'status' => 'draft',
            'filter_criteria' => ['tag' => 'cliente'],
        ];

        $response = $this->postJson('/api/chat/transmission-lists', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Lista de Teste',
                'message' => 'Olá {{name}}',
                'filter_criteria' => ['tag' => 'cliente'],
            ]);

        $this->assertDatabaseHas('chat_transmission_lists', [
            'name' => 'Lista de Teste',
            'tenant_id' => $this->user->tenant_id,
        ]);
    }

    public function test_can_list_transmission_lists(): void
    {
        ChatTransmissionList::factory()->count(3)->create([
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->getJson('/api/chat/transmission-lists');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_update_transmission_list(): void
    {
        $transmissionList = ChatTransmissionList::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'Original Name',
        ]);

        $payload = [
            'name' => 'Updated Name',
            'message' => 'New Message',
        ];

        $response = $this->putJson("/api/chat/transmission-lists/{$transmissionList->id}", $payload);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('chat_transmission_lists', [
            'id' => $transmissionList->id,
            'name' => 'Updated Name',
            'message' => 'New Message',
        ]);
    }

    public function test_can_delete_transmission_list(): void
    {
        $transmissionList = ChatTransmissionList::factory()->create([
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->deleteJson("/api/chat/transmission-lists/{$transmissionList->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('chat_transmission_lists', ['id' => $transmissionList->id]);
    }

    public function test_can_count_audience(): void
    {
        // Create 3 active contacts, 1 inactive
        \Domain\CRM\Models\CRMContact::factory()->count(3)->create([
            'tenant_id' => $this->user->tenant_id,
            'is_active' => true,
        ]);
        \Domain\CRM\Models\CRMContact::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/chat/transmission-lists/audience', [
            'criteria' => ['status' => 'active'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.count', 3);

        $responseAll = $this->postJson('/api/chat/transmission-lists/audience', [
            'criteria' => ['status' => 'all'],
        ]);
        $responseAll->assertJsonPath('data.count', 4);
    }
}
