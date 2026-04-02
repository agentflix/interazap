<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Jobs;

use Domain\Chat\Jobs\ProcessCampaignJob;
use Domain\Chat\Models\ChatCampaign;
use Domain\Chat\Models\ChatCampaignContact;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Services\ChatGatewayService;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ProcessCampaignJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_processes_pending_contacts_and_completes_campaign(): void
    {
        Queue::fake();

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'inst-token',
            'is_active' => true,
        ]);

        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'running',
            'instance_id' => $instance->id,
            'message' => 'Olá {{name}}',
            'metadata' => [],
        ]);

        $contacts = CRMContact::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->attachContacts($campaign, $contacts);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('sendText')->times(2)->andReturn(['messageid' => 'msg-1']);

        $job = new ProcessCampaignJob($campaign);
        $job->handle($gateway);

        $campaign->refresh();

        $this->assertSame('completed', $campaign->status);
        $this->assertSame(2, $campaign->metadata['deliveries'] ?? 0);
        $this->assertDatabaseHas('chat_campaign_contacts', [
            'campaign_id' => $campaign->id,
            'status' => 'sent',
        ]);

        Queue::assertNothingPushed();
    }

    public function test_reschedules_when_contacts_remain_pending(): void
    {
        Queue::fake();

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'inst-batch',
            'is_active' => true,
        ]);

        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'running',
            'instance_id' => $instance->id,
            'message' => 'Promoção',
            'metadata' => [],
        ]);

        $contacts = CRMContact::factory()->count(25)->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->attachContacts($campaign, $contacts);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('sendText')->times(20)->andReturn(['messageid' => 'batch']);

        $job = new ProcessCampaignJob($campaign);
        $job->handle($gateway);

        $this->assertSame(5, ChatCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->count());

        Queue::assertPushed(ProcessCampaignJob::class, 1);
    }

    public function test_marks_campaign_as_failed_when_instance_token_missing(): void
    {
        Queue::fake();

        $tenant = PlatformTenant::factory()->create();
        $campaign = ChatCampaign::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'running',
            'instance_id' => null,
            'message' => 'Mensagem',
            'metadata' => [],
        ]);

        $contact = CRMContact::factory()->create([
            'tenant_id' => $tenant->id,
            'whatsapp' => '5511999999999',
        ]);

        ChatCampaignContact::query()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldNotReceive('sendText');

        $job = new ProcessCampaignJob($campaign);
        $job->handle($gateway);

        $campaign->refresh();

        $this->assertSame('failed', $campaign->status);
        $this->assertSame('Instance token not found', $campaign->metadata['error'] ?? null);
    }

    /**
     * @param  Collection<int, CRMContact>  $contacts
     */
    private function attachContacts(ChatCampaign $campaign, Collection $contacts): void
    {
        foreach ($contacts as $contact) {
            ChatCampaignContact::query()->create([
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'status' => 'pending',
            ]);
        }
    }
}
