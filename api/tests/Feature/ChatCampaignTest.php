<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatCampaign;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatCampaignTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = AuthUser::factory()->create();

        // Setup permissions
        $permissions = [
            'chat.campaigns.view',
            'chat.campaigns.create',
            'chat.campaigns.update',
            'chat.campaigns.delete',
        ];

        foreach ($permissions as $perm) {
            \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }

        $this->user->givePermissionTo($permissions);

        $this->actingAs($this->user);
    }

    public function test_can_create_campaign(): void
    {
        $payload = [
            'name' => 'Campanha de Teste',
            'message' => 'Olá {{name}}',
            'status' => 'draft',
            'filter_criteria' => ['tag' => 'cliente'],
        ];

        $response = $this->postJson('/api/chat/campaigns', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Campanha de Teste',
                'message' => 'Olá {{name}}',
                'filter_criteria' => ['tag' => 'cliente'],
            ]);

        $this->assertDatabaseHas('chat_campaigns', [
            'name' => 'Campanha de Teste',
            'tenant_id' => $this->user->tenant_id,
        ]);
    }

    public function test_can_list_campaigns(): void
    {
        ChatCampaign::factory()->count(3)->create([
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->getJson('/api/chat/campaigns');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_update_campaign(): void
    {
        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'Original Name',
        ]);

        $payload = [
            'name' => 'Updated Name',
            'message' => 'New Message',
        ];

        $response = $this->putJson("/api/chat/campaigns/{$campaign->id}", $payload);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('chat_campaigns', [
            'id' => $campaign->id,
            'name' => 'Updated Name',
            'message' => 'New Message',
        ]);
    }

    public function test_can_delete_campaign(): void
    {
        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->deleteJson("/api/chat/campaigns/{$campaign->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('chat_campaigns', ['id' => $campaign->id]);
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

        $response = $this->postJson('/api/chat/campaigns/audience', [
            'criteria' => ['status' => 'active'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.count', 3);

        $responseAll = $this->postJson('/api/chat/campaigns/audience', [
            'criteria' => ['status' => 'all'],
        ]);
        $responseAll->assertJsonPath('data.count', 4);
    }
}
