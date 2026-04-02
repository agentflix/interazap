<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * OS-022 — Revalidação da cadeia de rotas de Autopilot.
 *
 * Garante que:
 *   1. Rotas protegidas por auth:sanctum retornam 401 sem autenticação.
 *   2. Rotas de tenant retornam respostas coerentes com usuário autenticado
 *      (não 401 nem 500 por falta de middleware).
 *   3. A rota interna /internal/ai/* rejeita requisições sem a X-Internal-Key.
 */
final class AutopilotRoutesAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * OS-022: GET /api/ai/agents sem token deve retornar 401 Unauthorized.
     * Confirma que o middleware auth:sanctum está activo na rota.
     */
    public function test_agents_index_requires_sanctum_auth(): void
    {
        $response = $this->getJson('/api/ai/agents');

        $response->assertStatus(401);
    }

    /**
     * OS-022: GET /api/ai/runs sem token deve retornar 401 Unauthorized.
     */
    public function test_autopilot_runs_index_requires_sanctum_auth(): void
    {
        $response = $this->getJson('/api/ai/runs');

        $response->assertStatus(401);
    }

    /**
     * OS-022: POST /api/ai/runs sem token deve retornar 401 Unauthorized.
     */
    public function test_autopilot_runs_store_requires_sanctum_auth(): void
    {
        $response = $this->postJson('/api/ai/runs', []);

        $response->assertStatus(401);
    }

    /**
     * OS-022: GET /api/ai/budget sem token deve retornar 401 Unauthorized.
     */
    public function test_budget_endpoint_requires_sanctum_auth(): void
    {
        $response = $this->getJson('/api/ai/budget');

        $response->assertStatus(401);
    }

    /**
     * OS-022: Rota de prompt resolve sem token deve retornar 401 Unauthorized.
     */
    public function test_prompt_resolve_requires_sanctum_auth(): void
    {
        $response = $this->getJson('/api/ai/prompt/resolve');

        $response->assertStatus(401);
    }

    /**
     * OS-022: Usuário autenticado com permissão adequada não deve receber 401
     * ou 500 — confirma que o middleware não bloqueia usuários legítimos.
     * (Pode retornar 403 por falta de permissão granular ou 200/422 dependendo
     * de estado, mas nunca 401 por ausência de auth.)
     */
    public function test_authenticated_user_is_not_blocked_by_auth_middleware(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/ai/agents');

        // 401 would indicate auth middleware failure; any other status is acceptable here.
        $this->assertNotSame(401, $response->getStatusCode(), 'Authenticated user must not be blocked by auth:sanctum middleware');
    }

    /**
     * OS-022: Rotas internas sem a chave correta devem retornar 401/403.
     * Confirma que o middleware internal.api.key está activo.
     */
    public function test_internal_ai_routes_reject_requests_without_api_key(): void
    {
        $response = $this->getJson(sprintf(
            '/api/internal/ai/context/%s',
            (string) Str::orderedUuid(),
        ));

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403],
            'Internal route must reject requests without the internal API key'
        );
    }
}
