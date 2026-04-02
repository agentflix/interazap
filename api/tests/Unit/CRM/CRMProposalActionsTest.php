<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use Domain\CRM\Actions\CRMProposalActions;
use Domain\CRM\DTOs\CRMProposalDTO;
use Domain\CRM\DTOs\CRMProposalItemDTO;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CRMProposalActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CRMProposalActions $actions;

    private PlatformTenant $tenant;

    private CRMNegotiation $negotiation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = PlatformTenant::factory()->create();
        $this->negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->actions = new CRMProposalActions;
    }

    private function makeItems(): array
    {
        return [
            new CRMProposalItemDTO(
                name: 'Licença',
                quantity: 2,
                unit_price: 50.0,
                discount: 0,
                position: 1,
            ),
            new CRMProposalItemDTO(
                name: 'Onboarding',
                quantity: 1,
                unit_price: 500.0,
                discount: 0,
                position: 2,
            ),
        ];
    }

    private function makeDto(array $overrides = [], ?array $items = null): CRMProposalDTO
    {
        return new CRMProposalDTO(
            crm_negotiation_id: $this->negotiation->id,
            title: $overrides['title'] ?? 'Proposta Padrão',
            number: $overrides['number'] ?? null,
            status: $overrides['status'] ?? 'draft',
            valid_until: $overrides['valid_until'] ?? now()->addWeek()->format('Y-m-d'),
            notes: $overrides['notes'] ?? null,
            items: $items ?? $this->makeItems(),
        );
    }

    public function test_create_syncs_items_and_total(): void
    {
        $proposal = $this->actions->create($this->tenant->id, $this->makeDto());

        $this->assertSame($this->tenant->id, $proposal->tenant_id);
        $this->assertCount(2, $proposal->items);
        $this->assertSame(600.0, (float) $proposal->total);
    }

    public function test_update_accepts_proposal_and_updates_negotiation(): void
    {
        $created = $this->actions->create($this->tenant->id, $this->makeDto());

        $updated = $this->actions->update($this->tenant->id, $created->id, $this->makeDto([
            'status' => 'accepted',
        ], [
            new CRMProposalItemDTO('Serviço', 3, 100.0, 0, 1),
        ]));

        $this->assertSame('accepted', $updated->status->value);
        $this->assertNotNull($updated->accepted_at);
        $this->assertNull($updated->rejected_at);
        $this->assertSame('won', $updated->negotiation->fresh()->status->value);
        $this->assertCount(1, $updated->items);
        $this->assertSame(300.0, (float) $updated->total);
    }

    public function test_send_generates_public_token_and_timestamps(): void
    {
        $proposal = $this->actions->create($this->tenant->id, $this->makeDto());

        $sent = $this->actions->send($this->tenant->id, $proposal->id);

        $this->assertSame('sent', $sent->status->value);
        $this->assertNotNull($sent->public_token);
        $this->assertNotNull($sent->sent_at);
        $this->assertNull($sent->accepted_at);
    }

    public function test_duplicate_copies_items_and_resets_state(): void
    {
        $proposal = $this->actions->create($this->tenant->id, $this->makeDto());

        $copy = $this->actions->duplicate($this->tenant->id, $proposal->id);

        $this->assertNotSame($proposal->id, $copy->id);
        $this->assertSame('draft', $copy->status->value);
        $this->assertStringContainsString('(Cópia)', $copy->title);
        $this->assertCount($proposal->items()->count(), $copy->items);
        $this->assertSame($proposal->total, $copy->total);
    }

    public function test_mark_viewed_sets_timestamp_only_once(): void
    {
        $proposal = $this->actions->create($this->tenant->id, $this->makeDto());
        $sent = $this->actions->send($this->tenant->id, $proposal->id);

        $firstView = $this->actions->markViewed($sent);
        $this->assertNotNull($firstView->viewed_at);

        $secondView = $this->actions->markViewed($firstView);
        $this->assertEquals($firstView->viewed_at->toDateTimeString(), $secondView->viewed_at->toDateTimeString());
    }
}
