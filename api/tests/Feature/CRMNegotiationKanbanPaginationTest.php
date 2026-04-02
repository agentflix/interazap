<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Testes de cursor-based pagination por etapa do kanban.
 */
class CRMNegotiationKanbanPaginationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    private function makeStep(string $tenantId, string $funnelId, int $order = 1): CRMNegotiationFunnelStep
    {
        return CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnelId,
            'order' => $order,
        ]);
    }

    /** Cria N negociações numa etapa com position incremental. */
    private function makeNegotiations(string $tenantId, string $funnelId, string $stepId, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            CRMNegotiation::factory()->create([
                'tenant_id' => $tenantId,
                'crm_negotiation_funnel_id' => $funnelId,
                'crm_negotiation_funnel_step_id' => $stepId,
                'position' => $i,
                'status' => 'open',
            ]);
        }
    }

    // =====================================================================
    // Casos básicos
    // =====================================================================

    public function test_step_with_zero_negotiations_returns_empty(): void
    {
        [$user, $tenantId] = $this->acting();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = $this->makeStep($tenantId, $funnel->id);

        $response = $this->getJson("/api/crm/negotiations-kanban/step/{$step->id}?funnel_id={$funnel->id}")
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertSame([], $data['negotiations']);
        $this->assertFalse($data['has_more']);
        $this->assertNull($data['next_cursor']);
    }

    public function test_step_with_few_negotiations_returns_all_without_cursor(): void
    {
        [$user, $tenantId] = $this->acting();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = $this->makeStep($tenantId, $funnel->id);
        $this->makeNegotiations($tenantId, $funnel->id, $step->id, 5);

        $response = $this->getJson("/api/crm/negotiations-kanban/step/{$step->id}?funnel_id={$funnel->id}&per_page=20")
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(5, $data['negotiations']);
        $this->assertFalse($data['has_more']);
        $this->assertNull($data['next_cursor']);
    }

    public function test_step_with_more_than_per_page_returns_has_more_and_cursor(): void
    {
        [$user, $tenantId] = $this->acting();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = $this->makeStep($tenantId, $funnel->id);
        $this->makeNegotiations($tenantId, $funnel->id, $step->id, 25);

        $response = $this->getJson("/api/crm/negotiations-kanban/step/{$step->id}?funnel_id={$funnel->id}&per_page=20")
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(20, $data['negotiations']);
        $this->assertTrue($data['has_more']);
        $this->assertNotNull($data['next_cursor']);
    }

    // =====================================================================
    // Cursor navigation
    // =====================================================================

    public function test_cursor_navigation_returns_correct_next_page(): void
    {
        [$user, $tenantId] = $this->acting();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = $this->makeStep($tenantId, $funnel->id);
        $this->makeNegotiations($tenantId, $funnel->id, $step->id, 25);

        // Primeira página
        $page1 = $this->getJson("/api/crm/negotiations-kanban/step/{$step->id}?funnel_id={$funnel->id}&per_page=20")
            ->assertStatus(200)
            ->json('data');

        $this->assertTrue($page1['has_more']);
        $cursor = $page1['next_cursor'];

        // Segunda página usando o cursor
        $page2 = $this->getJson("/api/crm/negotiations-kanban/step/{$step->id}?funnel_id={$funnel->id}&per_page=20&cursor={$cursor}")
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(5, $page2['negotiations']);
        $this->assertFalse($page2['has_more']);
        $this->assertNull($page2['next_cursor']);
    }

    public function test_cursor_pages_do_not_overlap(): void
    {
        [$user, $tenantId] = $this->acting();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = $this->makeStep($tenantId, $funnel->id);
        $this->makeNegotiations($tenantId, $funnel->id, $step->id, 25);

        $page1 = $this->getJson("/api/crm/negotiations-kanban/step/{$step->id}?funnel_id={$funnel->id}&per_page=20")
            ->assertStatus(200)->json('data');

        $page2 = $this->getJson("/api/crm/negotiations-kanban/step/{$step->id}?funnel_id={$funnel->id}&per_page=20&cursor={$page1['next_cursor']}")
            ->assertStatus(200)->json('data');

        $ids1 = array_column($page1['negotiations'], 'id');
        $ids2 = array_column($page2['negotiations'], 'id');

        $this->assertEmpty(array_intersect($ids1, $ids2), 'Páginas não devem ter itens em comum');
        $this->assertCount(25, array_unique(array_merge($ids1, $ids2)));
    }

    public function test_full_pagination_covers_all_items(): void
    {
        [$user, $tenantId] = $this->acting();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = $this->makeStep($tenantId, $funnel->id);
        $this->makeNegotiations($tenantId, $funnel->id, $step->id, 12);

        $allIds = [];
        $cursor = null;

        do {
            $url = "/api/crm/negotiations-kanban/step/{$step->id}?funnel_id={$funnel->id}&per_page=5";
            if ($cursor) {
                $url .= "&cursor={$cursor}";
            }

            $data = $this->getJson($url)->assertStatus(200)->json('data');
            $allIds = array_merge($allIds, array_column($data['negotiations'], 'id'));
            $cursor = $data['next_cursor'];
        } while ($data['has_more']);

        $this->assertCount(12, array_unique($allIds), 'Paginação deve cobrir todos os 12 itens sem duplicatas');
    }

    // =====================================================================
    // Isolamento de tenant
    // =====================================================================

    public function test_step_from_another_tenant_returns_empty_not_403(): void
    {
        [$userA, $tenantA] = $this->acting();
        $funnelA = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantA]);
        $stepA = $this->makeStep($tenantA, $funnelA->id);
        $this->makeNegotiations($tenantA, $funnelA->id, $stepA->id, 5);

        // Usuário B loga e tenta acessar o step do tenant A
        $userB = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($userB, abilities: ['*']);

        $funnelB = CRMNegotiationFunnel::factory()->create(['tenant_id' => $userB->tenant_id]);

        $response = $this->getJson("/api/crm/negotiations-kanban/step/{$stepA->id}?funnel_id={$funnelB->id}")
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertEmpty($data['negotiations'], 'Não deve retornar negociações de outro tenant');
    }

    // =====================================================================
    // Validação de request
    // =====================================================================

    public function test_kanban_step_requires_funnel_id(): void
    {
        [$user, $tenantId] = $this->acting();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = $this->makeStep($tenantId, $funnel->id);

        $this->getJson("/api/crm/negotiations-kanban/step/{$step->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['funnel_id']);
    }

    public function test_kanban_step_rejects_invalid_per_page(): void
    {
        [$user, $tenantId] = $this->acting();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = $this->makeStep($tenantId, $funnel->id);

        $this->getJson("/api/crm/negotiations-kanban/step/{$step->id}?funnel_id={$funnel->id}&per_page=100")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_kanban_step_response_has_required_fields(): void
    {
        [$user, $tenantId] = $this->acting();
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = $this->makeStep($tenantId, $funnel->id);
        $this->makeNegotiations($tenantId, $funnel->id, $step->id, 1);

        $data = $this->getJson("/api/crm/negotiations-kanban/step/{$step->id}?funnel_id={$funnel->id}")
            ->assertStatus(200)
            ->json('data');

        $this->assertArrayHasKey('negotiations', $data);
        $this->assertArrayHasKey('has_more', $data);
        $this->assertArrayHasKey('next_cursor', $data);

        $negotiation = $data['negotiations'][0];
        $this->assertArrayHasKey('id', $negotiation);
        $this->assertArrayHasKey('title', $negotiation);
        $this->assertArrayHasKey('value', $negotiation);
        $this->assertArrayHasKey('status', $negotiation);
        $this->assertArrayHasKey('position', $negotiation);
        $this->assertArrayHasKey('step_id', $negotiation);
        $this->assertArrayHasKey('funnel_id', $negotiation);
    }

    public function test_unauthenticated_user_is_rejected(): void
    {
        $this->getJson('/api/crm/negotiations-kanban/step/any-uuid?funnel_id=any-uuid')
            ->assertStatus(401);
    }
}
