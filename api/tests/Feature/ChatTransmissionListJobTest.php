<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Jobs\ProcessTransmissionListJob;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTransmissionList;
use Domain\Chat\Models\ChatTransmissionListContact;
use Domain\Chat\Services\ChatGatewayService;
use Domain\CRM\Models\CRMContact;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatTransmissionListJobTest extends TestCase
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
            'chat.transmission_lists.view',
            'chat.transmission_lists.create',
            'chat.transmission_lists.update',
            'chat.transmission_lists.delete',
        ];

        foreach ($permissions as $perm) {
            AuthPermission::query()->firstOrCreate(['name' => $perm], ['guard_name' => 'sanctum']);
        }
        $this->user->givePermissionTo($permissions);
        $this->actingAs($this->user, 'sanctum');

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

        // Create Transmission List
        $transmissionList = ChatTransmissionList::factory()->create([
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
        ChatTransmissionListContact::query()->create([
            'id' => (string) Str::uuid(),
            'transmission_list_id' => $transmissionList->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
        ]);

        // Run Job
        $job = new ProcessTransmissionListJob($transmissionList);
        $job->handle(app(ChatGatewayService::class));

        // Assertions
        $this->assertDatabaseHas('chat_transmission_list_contacts', [
            'transmission_list_id' => $transmissionList->id,
            'contact_id' => $contact->id,
            'status' => 'sent',
        ]);

        $transmissionList->refresh();
        $this->assertEquals('completed', $transmissionList->status);
        $this->assertEquals(1, $transmissionList->metadata['deliveries']);
    }

    public function test_preview_endpoint_returns_replaced_message(): void
    {
        CRMContact::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'Jane Doe',
            'phone' => '5511888888888',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/chat/transmission-lists/preview', [
            'message' => 'Hi {{name}}, call {{phone}}',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.preview', 'Hi Jane Doe, call 5511888888888')
            ->assertJsonPath('data.sample_contact.name', 'Jane Doe');
    }
}
