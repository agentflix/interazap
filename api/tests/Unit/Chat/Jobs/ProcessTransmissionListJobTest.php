<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Jobs;

use Domain\Chat\Jobs\ProcessTransmissionListJob;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTransmissionList;
use Domain\Chat\Models\ChatTransmissionListContact;
use Domain\Chat\Services\ChatGatewayService;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ProcessTransmissionListJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_processes_pending_contacts_and_completes_transmission_list(): void
    {
        Queue::fake();

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'inst-token',
            'is_active' => true,
        ]);

        $transmissionList = ChatTransmissionList::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'running',
            'instance_id' => $instance->id,
            'message' => 'Olá {{name}}',
            'metadata' => [],
        ]);

        $contacts = CRMContact::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->attachContacts($transmissionList, $contacts);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('sendText')->times(2)->andReturn(['messageid' => 'msg-1']);

        $job = new ProcessTransmissionListJob($transmissionList);
        $job->handle($gateway);

        $transmissionList->refresh();

        $this->assertSame('completed', $transmissionList->status);
        $this->assertSame(2, $transmissionList->metadata['deliveries'] ?? 0);
        $this->assertDatabaseHas('chat_transmission_list_contacts', [
            'transmission_list_id' => $transmissionList->id,
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

        $transmissionList = ChatTransmissionList::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'running',
            'instance_id' => $instance->id,
            'message' => 'Promoção',
            'metadata' => [],
        ]);

        $contacts = CRMContact::factory()->count(25)->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->attachContacts($transmissionList, $contacts);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('sendText')->times(20)->andReturn(['messageid' => 'batch']);

        $job = new ProcessTransmissionListJob($transmissionList);
        $job->handle($gateway);

        $this->assertSame(5, ChatTransmissionListContact::query()
            ->where('transmission_list_id', $transmissionList->id)
            ->where('status', 'pending')
            ->count());

        Queue::assertPushed(ProcessTransmissionListJob::class, 1);
    }

    public function test_marks_transmission_list_as_failed_when_instance_token_missing(): void
    {
        Queue::fake();

        $tenant = PlatformTenant::factory()->create();
        $transmissionList = ChatTransmissionList::factory()->create([
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

        ChatTransmissionListContact::query()->create([
            'transmission_list_id' => $transmissionList->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldNotReceive('sendText');

        $job = new ProcessTransmissionListJob($transmissionList);
        $job->handle($gateway);

        $transmissionList->refresh();

        $this->assertSame('failed', $transmissionList->status);
        $this->assertSame('Instance token not found', $transmissionList->metadata['error'] ?? null);
    }

    /**
     * @param  Collection<int, CRMContact>  $contacts
     */
    private function attachContacts(ChatTransmissionList $transmissionList, Collection $contacts): void
    {
        foreach ($contacts as $contact) {
            ChatTransmissionListContact::query()->create([
                'transmission_list_id' => $transmissionList->id,
                'contact_id' => $contact->id,
                'status' => 'pending',
            ]);
        }
    }
}
