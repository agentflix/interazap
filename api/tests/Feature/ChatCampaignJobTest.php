<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Jobs\ProcessCampaignJob;
use Domain\Chat\Models\ChatCampaign;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Services\ChatGatewayService;
use Domain\CRM\Models\CRMContact;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatCampaignJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    private ChatInstance $instance;

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

        // Instance
        $this->instance = ChatInstance::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'webhook_token' => 'test-token',
            'is_active' => true,
        ]);
    }

    public function test_job_processes_pending_contacts(): void
    {
        // Mock Gateway
        $this->mock(ChatGatewayService::class, function ($mock): void {
            $mock->shouldReceive('sendText')
                ->once()
                ->with('test-token', \Mockery::on(fn ($data): bool => $data['number'] === '5511999999999' && $data['text'] === 'Hello John Doe'))
                ->andReturn(['id' => 'msg-123']);
        });

        // Create Campaign
        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'instance_id' => $this->instance->id,
            'message' => 'Hello {{name}}',
            'status' => 'running',
        ]);

        // Create Contact
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'John Doe',
            'whatsapp' => '5511999999999',
            'is_active' => true,
        ]);

        // Attach Contact
        \Domain\Chat\Models\ChatCampaignContact::query()->create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
        ]);

        // Run Job
        $job = new ProcessCampaignJob($campaign);
        $job->handle(app(ChatGatewayService::class));

        // Assertions
        $this->assertDatabaseHas('chat_campaign_contacts', [
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'sent',
        ]);

        $campaign->refresh();
        $this->assertEquals('completed', $campaign->status); // Completed because list is empty
        $this->assertEquals(1, $campaign->metadata['deliveries']);
    }

    public function test_preview_endpoint_returns_replaced_message(): void
    {
        CRMContact::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'Jane Doe',
            'phone' => '5511888888888',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/chat/campaigns/preview', [
            'message' => 'Hi {{name}}, call {{phone}}',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.preview', 'Hi Jane Doe, call 5511888888888')
            ->assertJsonPath('data.sample_contact.name', 'Jane Doe');
    }
}
