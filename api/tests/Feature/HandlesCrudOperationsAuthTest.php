<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * TASK-013-S1-SEC / API-SEC-005
 * Garante que crudUpdate autoriza com o modelo real e passa o modelo ao callback de update.
 */
final class HandlesCrudOperationsAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * O crudUpdate deve autorizar usando o modelo real e o callback deve receber o modelo.
     */
    public function test_crud_update_concern_enforces_authorization_with_real_model(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);

        $tag = CRMTag::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Original',
        ]);

        // Usuário do mesmo tenant pode atualizar a tag
        $response = $this->actingAs($user)
            ->putJson("/api/crm/tags/{$tag->id}", ['name' => 'Atualizada']);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Atualizada']);

        $tag->refresh();
        $this->assertSame('Atualizada', $tag->name);
    }

    /**
     * Usuário de tenant diferente não pode atualizar via crudUpdate (403).
     */
    public function test_crud_update_concern_denies_cross_tenant_update(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        $userA = AuthUser::factory()->create(['tenant_id' => $tenantA->id]);

        // Tag criada para tenantB
        $tagOfTenantB = CRMTag::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tag do Tenant B',
        ]);

        // Usuário do tenantA tentando atualizar tag do tenantB — deve ser bloqueado
        $response = $this->actingAs($userA)
            ->putJson("/api/crm/tags/{$tagOfTenantB->id}", ['name' => 'Hackeada']);

        // Deve retornar 404 (TenantScope filtra) ou 403 (política bloqueia)
        $this->assertContains(
            $response->status(),
            [403, 404],
            'Acesso cross-tenant ao recurso de outro tenant deve ser negado.'
        );

        // Tag não deve ter sido alterada
        $tagOfTenantB->refresh();
        $this->assertSame('Tag do Tenant B', $tagOfTenantB->name);
    }

    /**
     * Requisição sem autenticação retorna 401.
     */
    public function test_crud_update_requires_authentication(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tag = CRMTag::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Tag']);

        $this->putJson("/api/crm/tags/{$tag->id}", ['name' => 'Nova'])
            ->assertStatus(401);
    }
}
