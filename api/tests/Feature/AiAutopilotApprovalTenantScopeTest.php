<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Models\AiAutopilotApproval;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Domain\Shared\Scopes\TenantScope;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * TASK-013-S1-SEC / API-SEC-003
 * Garante que AiAutopilotApproval tem isolamento de tenant (BelongsToTenant).
 */
final class AiAutopilotApprovalTenantScopeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * O modelo deve usar o trait BelongsToTenant.
     */
    public function test_ai_autopilot_approval_model_is_tenant_scoped(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        $runA = AiAutopilotRun::factory()->create(['tenant_id' => $tenantA->id]);
        $runB = AiAutopilotRun::factory()->create(['tenant_id' => $tenantB->id]);

        $approvalA = AiAutopilotApproval::factory()->create([
            'tenant_id' => $tenantA->id,
            'run_id' => $runA->id,
        ]);

        $approvalB = AiAutopilotApproval::factory()->create([
            'tenant_id' => $tenantB->id,
            'run_id' => $runB->id,
        ]);

        // O modelo deve usar a trait BelongsToTenant
        $traits = class_uses_recursive(AiAutopilotApproval::class);
        $this->assertArrayHasKey(BelongsToTenant::class, $traits, 'AiAutopilotApproval deve usar BelongsToTenant.');

        // O global scope de tenant deve estar registrado
        $model = new AiAutopilotApproval;
        $scopes = $model->getGlobalScopes();
        $this->assertArrayHasKey(TenantScope::class, $scopes, 'TenantScope deve estar aplicado ao modelo.');

        // O tenant_id deve estar no fillable
        $this->assertContains('tenant_id', $approvalA->getFillable());

        // O approval deve estar persistido com tenant_id correto
        $this->assertSame((string) $tenantA->id, (string) $approvalA->tenant_id);
        $this->assertSame((string) $tenantB->id, (string) $approvalB->tenant_id);
    }

    /**
     * Queries no modelo devem retornar apenas registros do tenant correto via TenantScope.
     */
    public function test_approval_query_is_filtered_by_tenant_scope(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        $userA = AuthUser::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = AuthUser::factory()->create(['tenant_id' => $tenantB->id]);

        $runA = AiAutopilotRun::factory()->create(['tenant_id' => $tenantA->id]);
        $runB = AiAutopilotRun::factory()->create(['tenant_id' => $tenantB->id]);

        AiAutopilotApproval::factory()->create([
            'tenant_id' => $tenantA->id,
            'run_id' => $runA->id,
        ]);

        AiAutopilotApproval::factory()->create([
            'tenant_id' => $tenantB->id,
            'run_id' => $runB->id,
        ]);

        // Query sem scope explícito de tenant deve retornar todos os registros apenas quando o TenantScope é bypassado.
        $totalWithoutScope = AiAutopilotApproval::query()
            ->withoutGlobalScope(TenantScope::class)
            ->count();

        $this->assertSame(2, $totalWithoutScope, 'Sem scope, devem existir 2 approvals no banco.');

        // Com autenticação do tenant A, o escopo global deve filtrar para apenas 1 registro do tenant A.
        $this->actingAs($userA);
        $forTenantA = AiAutopilotApproval::query()->get();

        $this->assertCount(1, $forTenantA);
        $this->assertSame((string) $tenantA->id, (string) $forTenantA->first()?->tenant_id);

        // Com autenticação do tenant B, o escopo global deve filtrar para apenas 1 registro do tenant B.
        $this->actingAs($userB);
        $forTenantB = AiAutopilotApproval::query()->get();

        $this->assertCount(1, $forTenantB);
        $this->assertSame((string) $tenantB->id, (string) $forTenantB->first()?->tenant_id);
    }
}
