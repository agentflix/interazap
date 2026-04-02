<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use Domain\CRM\Actions\CRMNegotiationActions;
use Domain\CRM\DTOs\CRMNegotiationDTO;
use Domain\CRM\DTOs\CRMNegotiationTaskDTO;
use Domain\CRM\Enums\CRMNegotiationStatus;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMReasonLoss;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Contracts\ActivityBroadcastService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CRMNegotiationActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CRMNegotiationActions $actions;

    private PlatformTenant $tenant;

    private CRMNegotiationFunnel $funnel;

    private CRMNegotiationFunnelStep $stepA;

    private CRMNegotiationFunnelStep $stepB;

    private CRMContact $contact;

    private ActivityBroadcastService&MockInterface $broadcast;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = PlatformTenant::factory()->create();
        $this->funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->stepA = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'order' => 1,
        ]);
        $this->stepB = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'order' => 2,
        ]);
        $this->contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->broadcast = Mockery::spy(ActivityBroadcastService::class);
        $this->app->instance(ActivityBroadcastService::class, $this->broadcast);
        $this->actions = $this->app->make(CRMNegotiationActions::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeDto(array $overrides = []): CRMNegotiationDTO
    {
        return CRMNegotiationDTO::fromArray(array_merge([
            'title' => 'Opportunity',
            'amount' => 1200,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->stepA->id,
            'crm_company_id' => null,
            'crm_contact_id' => $this->contact->id,
            'crm_reason_loss_id' => null,
            'status' => CRMNegotiationStatus::OPEN->value,
            'position' => 0,
            'expected_close' => \Illuminate\Support\Facades\Date::now()->addWeek()->toDateString(),
        ], $overrides));
    }

    public function test_create_assigns_next_position_and_loads_relations(): void
    {
        CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->stepA->id,
            'position' => 1,
        ]);

        $negotiation = $this->actions->create($this->tenant->id, $this->makeDto());

        $this->assertSame(2, $negotiation->position);
        $this->assertTrue($negotiation->relationLoaded('step'));
        $this->assertSame($this->stepA->id, $negotiation->crm_negotiation_funnel_step_id);
    }

    public function test_move_reorders_target_column(): void
    {
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->stepA->id,
            'position' => 1,
        ]);
        $targetFirst = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->stepB->id,
            'position' => 1,
        ]);
        $targetSecond = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->stepB->id,
            'position' => 2,
        ]);

        $result = $this->actions->move($this->tenant->id, $negotiation->id, $this->stepB->id, 2);

        $this->assertSame($this->stepB->id, $result->crm_negotiation_funnel_step_id);
        $this->assertSame(2, $result->position);

        $ordered = CRMNegotiation::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('crm_negotiation_funnel_step_id', $this->stepB->id)
            ->orderBy('position')
            ->pluck('id')
            ->all();

        $this->assertSame([$targetFirst->id, $result->id, $targetSecond->id], $ordered);
    }

    public function test_mark_won_sets_status_and_broadcasts_payload(): void
    {
        $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->stepA->id,
            'crm_contact_id' => $contact->id,
            'status' => CRMNegotiationStatus::OPEN,
        ]);

        $result = $this->actions->markWon($this->tenant->id, $negotiation->id);

        $this->assertSame(CRMNegotiationStatus::WON, $result->status);
        $this->assertNotNull($result->closed_at);
        $this->broadcast->shouldHaveReceived('broadcastNegotiationStatusChanged')
            ->once()
            ->with(
                $this->tenant->id,
                $contact->id,
                Mockery::on(fn ($payload): bool => isset($payload['negotiation_id'], $payload['status'])
                    && $payload['negotiation_id'] === $negotiation->id
                    && $payload['status'] === CRMNegotiationStatus::WON->value)
            );
    }

    public function test_mark_lost_requires_reason(): void
    {
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->stepA->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->actions->markLost($this->tenant->id, $negotiation->id, null);
    }

    public function test_mark_lost_sets_reason_and_broadcasts(): void
    {
        $contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);
        $reason = CRMReasonLoss::factory()->create(['tenant_id' => $this->tenant->id]);
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->stepA->id,
            'crm_contact_id' => $contact->id,
        ]);

        $result = $this->actions->markLost($this->tenant->id, $negotiation->id, $reason->id);

        $this->assertSame(CRMNegotiationStatus::LOST, $result->status);
        $this->assertSame($reason->id, $result->crm_reason_loss_id);
        $this->broadcast->shouldHaveReceived('broadcastNegotiationStatusChanged')
            ->once()
            ->with(
                $this->tenant->id,
                $contact->id,
                Mockery::on(fn ($payload): bool => isset($payload['negotiation_id'], $payload['status'], $payload['reason_loss_id'])
                    && $payload['negotiation_id'] === $negotiation->id
                    && $payload['status'] === CRMNegotiationStatus::LOST->value
                    && $payload['reason_loss_id'] === $reason->id)
            );
    }

    public function test_reopen_resets_status_and_closed_at(): void
    {
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->stepA->id,
            'status' => CRMNegotiationStatus::WON,
            'closed_at' => now(),
        ]);

        $result = $this->actions->reopen($this->tenant->id, $negotiation->id);

        $this->assertSame(CRMNegotiationStatus::OPEN, $result->status);
        $this->assertNull($result->closed_at);
    }

    public function test_create_task_persists_task_data(): void
    {
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $this->funnel->id,
            'crm_negotiation_funnel_step_id' => $this->stepA->id,
        ]);

        $task = $this->actions->createTask($this->tenant->id, new CRMNegotiationTaskDTO(
            crm_negotiation_id: $negotiation->id,
            title: 'Follow up',
            description: 'Ligar para o cliente',
            due_date: now()->addDay()->toDateString(),
            status: 'pending',
        ));

        $this->assertSame('Follow up', $task->title);
        $this->assertSame($negotiation->id, $task->crm_negotiation_id);
    }
}
