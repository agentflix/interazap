<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMFunnelsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_cria_funil_e_etapa(): void
    {
        [$user, $tenantId] = $this->acting();

        $funnel = $this->postJson('/api/crm/funnels', [
            'name' => 'Funil A',
            'description' => 'Descrição inicial',
            'is_active' => true,
            'steps' => [
                [
                    'name' => 'Contato inicial',
                    'order' => 1,
                    'color' => '#3b82f6',
                    'is_active' => true,
                ],
            ],
        ])
            ->assertStatus(201)
            ->json('data');

        $this->assertSame('Descrição inicial', $funnel['description']);

        $step = $this->postJson('/api/crm/funnels/'.$funnel['id'].'/steps', [
            'name' => 'Contato inicial',
            'order' => 2,
            'color' => '#3b82f6',
            'is_active' => true,
        ])->assertStatus(201)->json('data');

        $this->getJson('/api/crm/funnels/'.$funnel['id'].'/steps')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.steps');

        $this->putJson('/api/crm/funnels/'.$funnel['id'].'/steps/'.$step['id'], [
            'name' => 'Contato atualizado',
            'color' => '#22c55e',
            'is_active' => false,
        ])->assertStatus(200);

        $this->deleteJson('/api/crm/funnels/'.$funnel['id'].'/steps/'.$step['id'])
            ->assertStatus(204);

        $this->putJson('/api/crm/funnels/'.$funnel['id'], [
            'name' => 'Funil B',
            'description' => 'Descrição atualizada',
            'is_active' => false,
            'steps' => [
                [
                    'name' => 'Contato inicial',
                    'order' => 1,
                    'color' => '#3b82f6',
                    'is_active' => true,
                ],
            ],
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.description', 'Descrição atualizada');

        $this->getJson('/api/crm/funnels?search=atualizada')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        $all = $this->getJson('/api/crm/funnels/all')->assertStatus(200)->json('data');
        $this->assertNotEmpty($all);
    }
}
