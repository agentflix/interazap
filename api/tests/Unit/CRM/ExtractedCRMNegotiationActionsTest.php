<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use Domain\CRM\Actions\ChangeNegotiationStageAction;
use Domain\CRM\Actions\ListCRMNegotiationsAction;
use Domain\CRM\Enums\CRMNegotiationStatus;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMReasonLoss;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExtractedCRMNegotiationActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_list_action_filters_by_tenant_and_status(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenant->id]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);

        CRMNegotiation::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_contact_id' => $contact->id,
            'status' => 'open',
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_contact_id' => $contact->id,
            'status' => 'won',
        ]);

        $otherFunnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherStep = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $otherTenant->id,
            'crm_negotiation_funnel_id' => $otherFunnel->id,
            'order' => 1,
        ]);
        $otherContact = CRMContact::factory()->create(['tenant_id' => $otherTenant->id]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $otherTenant->id,
            'crm_negotiation_funnel_id' => $otherFunnel->id,
            'crm_negotiation_funnel_step_id' => $otherStep->id,
            'crm_contact_id' => $otherContact->id,
            'status' => 'open',
        ]);

        /** @var ListCRMNegotiationsAction $action */
        $action = app(ListCRMNegotiationsAction::class);
        $result = $action->list((string) $tenant->id, [
            'status' => 'open',
            'per_page' => 50,
        ]);

        $this->assertSame(2, $result->total());
    }

    public function test_change_stage_action_moves_negotiation_and_reorders_positions(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenant->id]);
        $fromStep = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);
        $toStep = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 2,
        ]);
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);

        $moving = CRMNegotiation::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $fromStep->id,
            'crm_contact_id' => $contact->id,
            'position' => 1,
        ]);

        CRMNegotiation::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $toStep->id,
            'crm_contact_id' => $contact->id,
            'position' => 1,
        ]);

        /** @var ChangeNegotiationStageAction $action */
        $action = app(ChangeNegotiationStageAction::class);
        $updated = $action->move((string) $tenant->id, (string) $moving->id, (string) $toStep->id, 1);

        $this->assertSame((string) $toStep->id, (string) $updated->crm_negotiation_funnel_step_id);
        $this->assertSame(1, (int) $updated->position);

        $orderedIds = CRMNegotiation::query()
            ->where('tenant_id', $tenant->id)
            ->where('crm_negotiation_funnel_step_id', $toStep->id)
            ->orderBy('position')
            ->pluck('id')
            ->all();

        $this->assertSame((string) $moving->id, (string) $orderedIds[0]);
    }

    public function test_change_stage_action_mark_won_applies_side_effects(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenant->id]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $reason = CRMReasonLoss::factory()->create(['tenant_id' => $tenant->id]);

        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_contact_id' => $contact->id,
            'crm_reason_loss_id' => $reason->id,
            'status' => CRMNegotiationStatus::OPEN,
            'closed_at' => null,
        ]);

        /** @var ChangeNegotiationStageAction $action */
        $action = app(ChangeNegotiationStageAction::class);
        $updated = $action->markWon((string) $tenant->id, (string) $negotiation->id);

        $this->assertSame(CRMNegotiationStatus::WON, $updated->status);
        $this->assertNotNull($updated->closed_at);
        $this->assertNull($updated->crm_reason_loss_id);
    }

    public function test_change_stage_action_mark_lost_and_reopen_apply_side_effects(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenant->id]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $reason = CRMReasonLoss::factory()->create(['tenant_id' => $tenant->id]);

        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'crm_contact_id' => $contact->id,
            'status' => CRMNegotiationStatus::OPEN,
            'closed_at' => null,
        ]);

        /** @var ChangeNegotiationStageAction $action */
        $action = app(ChangeNegotiationStageAction::class);
        $lost = $action->markLost((string) $tenant->id, (string) $negotiation->id, (string) $reason->id);

        $this->assertSame(CRMNegotiationStatus::LOST, $lost->status);
        $this->assertSame((string) $reason->id, (string) $lost->crm_reason_loss_id);
        $this->assertNotNull($lost->closed_at);

        $reopened = $action->reopen((string) $tenant->id, (string) $negotiation->id);

        $this->assertSame(CRMNegotiationStatus::OPEN, $reopened->status);
        $this->assertNull($reopened->crm_reason_loss_id);
        $this->assertNull($reopened->closed_at);
    }
}
