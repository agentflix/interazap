<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use Domain\CRM\Actions\CRMFunnelActions;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @group crm
 * @group funnel
 */
class CRMFunnelActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CRMFunnelActions $actions;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actions = new CRMFunnelActions;
        $tenant = PlatformTenant::factory()->create();
        $this->tenantId = $tenant->id;
    }

    public function test_list_returns_paginated_funnels_with_steps(): void
    {
        CRMNegotiationFunnel::factory()
            ->has(CRMNegotiationFunnelStep::factory()->count(3), 'steps')
            ->count(5)
            ->create(['tenant_id' => $this->tenantId]);

        $result = $this->actions->list($this->tenantId);

        expect($result->total())->toBe(5);
        expect($result->first()->steps)->toHaveCount(3);
    }

    public function test_list_filters_by_tenant(): void
    {
        CRMNegotiationFunnel::factory()->count(3)->create(['tenant_id' => $this->tenantId]);
        $otherTenant = PlatformTenant::factory()->create();
        CRMNegotiationFunnel::factory()->count(2)->create(['tenant_id' => $otherTenant->id]);

        $result = $this->actions->list($this->tenantId);

        expect($result->total())->toBe(3);
    }

    public function test_all_returns_collection_of_funnels(): void
    {
        CRMNegotiationFunnel::factory()->count(3)->create(['tenant_id' => $this->tenantId]);
        $otherTenant = PlatformTenant::factory()->create();
        CRMNegotiationFunnel::factory()->count(2)->create(['tenant_id' => $otherTenant->id]);

        $result = $this->actions->all($this->tenantId);

        expect($result)->toHaveCount(3);
        expect($result->first())->toBeInstanceOf(CRMNegotiationFunnel::class);
    }

    public function test_create_funnel_with_valid_name(): void
    {
        $funnel = $this->actions->create($this->tenantId, 'Vendas Corporativas');

        expect($funnel)
            ->toBeInstanceOf(CRMNegotiationFunnel::class)
            ->tenant_id->toBe($this->tenantId)
            ->name->toBe('Vendas Corporativas')
            ->is_active->toBeTrue();

        $this->assertDatabaseHas('crm_negotiation_funnels', [
            'id' => $funnel->id,
            'tenant_id' => $this->tenantId,
            'name' => 'Vendas Corporativas',
        ]);
    }

    public function test_create_throws_exception_for_duplicate_name_in_same_tenant(): void
    {
        $this->actions->create($this->tenantId, 'Funil A');

        expect(fn (): \Domain\CRM\Models\CRMNegotiationFunnel => $this->actions->create($this->tenantId, 'Funil A'))
            ->toThrow(ValidationException::class, 'Funil já cadastrado');
    }

    public function test_create_allows_duplicate_name_across_different_tenants(): void
    {
        $otherTenant = PlatformTenant::factory()->create();
        $funnelA = $this->actions->create($this->tenantId, 'Funil Padrão');
        $funnelB = $this->actions->create($otherTenant->id, 'Funil Padrão');

        expect($funnelA->name)->toBe('Funil Padrão');
        expect($funnelB->name)->toBe('Funil Padrão');
        expect($funnelA->tenant_id)->not->toBe($funnelB->tenant_id);
    }

    public function test_update_funnel_name(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Nome Antigo',
        ]);

        $updated = $this->actions->update($this->tenantId, $funnel->id, [
            'name' => 'Nome Novo',
        ]);

        expect($updated->name)->toBe('Nome Novo');
        $this->assertDatabaseHas('crm_negotiation_funnels', [
            'id' => $funnel->id,
            'name' => 'Nome Novo',
        ]);
    }

    public function test_update_funnel_status(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $this->tenantId,
            'is_active' => true,
        ]);

        $updated = $this->actions->update($this->tenantId, $funnel->id, [
            'is_active' => false,
        ]);

        expect($updated->is_active)->toBeFalse();
    }

    public function test_update_throws_exception_if_new_name_already_exists(): void
    {
        CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Funil Existente',
        ]);

        $funnel = CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Funil A Atualizar',
        ]);

        expect(fn (): \Domain\CRM\Models\CRMNegotiationFunnel => $this->actions->update($this->tenantId, $funnel->id, [
            'name' => 'Funil Existente',
        ]))->toThrow(ValidationException::class);
    }

    public function test_delete_removes_funnel(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);

        $this->actions->delete($this->tenantId, $funnel->id);

        $this->assertDatabaseMissing('crm_negotiation_funnels', [
            'id' => $funnel->id,
        ]);
    }

    public function test_delete_enforces_tenant_isolation(): void
    {
        $otherTenant = PlatformTenant::factory()->create();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->actions->delete($this->tenantId, $funnel->id);
    }

    public function test_add_step_to_funnel(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);

        $step = $this->actions->addStep($this->tenantId, $funnel->id, 'Prospecção', 1);

        expect($step)
            ->toBeInstanceOf(CRMNegotiationFunnelStep::class)
            ->tenant_id->toBe($this->tenantId)
            ->crm_negotiation_funnel_id->toBe($funnel->id)
            ->name->toBe('Prospecção')
            ->order->toBe(1);

        $this->assertDatabaseHas('crm_negotiation_funnel_steps', [
            'id' => $step->id,
            'name' => 'Prospecção',
        ]);
    }

    public function test_reorder_steps(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);

        $step1 = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);

        $step2 = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 2,
        ]);

        $step3 = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 3,
        ]);

        $this->actions->reorder($this->tenantId, $funnel->id, [
            ['id' => $step3->id, 'order' => 1],
            ['id' => $step1->id, 'order' => 2],
            ['id' => $step2->id, 'order' => 3],
        ]);

        $step1->refresh();
        $step2->refresh();
        $step3->refresh();

        expect($step3->order)->toBe(1);
        expect($step1->order)->toBe(2);
        expect($step2->order)->toBe(3);
    }

    public function test_find_returns_funnel_with_steps(): void
    {
        $funnel = CRMNegotiationFunnel::factory()
            ->has(CRMNegotiationFunnelStep::factory()->count(3), 'steps')
            ->create(['tenant_id' => $this->tenantId]);

        $found = $this->actions->find($this->tenantId, $funnel->id);

        expect($found->id)->toBe($funnel->id);
        expect($found->steps)->toHaveCount(3);
    }

    public function test_find_enforces_tenant_isolation(): void
    {
        $otherTenant = PlatformTenant::factory()->create();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->actions->find($this->tenantId, $funnel->id);
    }

    public function test_add_step_enforces_tenant_isolation(): void
    {
        $otherTenant = PlatformTenant::factory()->create();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->actions->addStep($this->tenantId, $funnel->id, 'Step', 1);
    }

    public function test_reorder_enforces_tenant_isolation(): void
    {
        $otherTenant = PlatformTenant::factory()->create();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $otherTenant->id]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $otherTenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->actions->reorder($this->tenantId, $funnel->id, [
            ['id' => $step->id, 'order' => 1],
        ]);
    }
}
