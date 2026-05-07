<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformLead;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformLeadAdminControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeAdmin(): AuthUser
    {
        $admin = AuthUser::factory()->create();
        AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );
        $admin->assignRole(AuthRole::INQUILINO_ID);

        return $admin->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLead(array $overrides = []): PlatformLead
    {
        return PlatformLead::query()->create(array_merge([
            'name' => 'Lead '.substr((string) str()->uuid(), 0, 8),
            'phone' => '11999999999',
            'email' => 'lead-'.str()->lower((string) str()->random(8)).'@example.com',
            'company' => 'Empresa Teste',
            'source' => 'landing_form',
            'lgpd_consent' => true,
            'status' => 'new',
        ], $overrides));
    }

    public function test_admin_can_list_platform_leads(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $this->createLead(['name' => 'Alice Lead']);
        $this->createLead(['name' => 'Bruno Lead']);

        $this->getJson('/api/platform/leads')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_non_admin_cannot_list_platform_leads(): void
    {
        $user = AuthUser::factory()->create();
        Sanctum::actingAs($user, abilities: ['*']);

        $this->createLead();

        $this->getJson('/api/platform/leads')
            ->assertStatus(403);
    }

    public function test_admin_can_filter_by_search_status_and_source(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $this->createLead([
            'name' => 'João Qualificado',
            'status' => 'qualified',
            'source' => 'landing_exit_modal',
            'email' => 'joao-qualified@example.com',
        ]);

        $this->createLead([
            'name' => 'Maria Novo',
            'status' => 'new',
            'source' => 'landing_form',
            'email' => 'maria-new@example.com',
        ]);

        $response = $this->getJson('/api/platform/leads?search=João&status=qualified&source=landing_exit_modal');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'joao-qualified@example.com');
    }
}
