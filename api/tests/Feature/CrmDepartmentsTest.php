<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMDepartmentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_crud_departments(): void
    {
        [$user, $tenantId] = $this->acting();

        $department = $this->postJson('/api/crm/departments', [
            'name' => 'Comercial',
            'description' => 'Time comercial',
            'is_active' => true,
        ])->assertStatus(201)->json('data');

        $this->putJson('/api/crm/departments/'.$department['id'], [
            'name' => 'Comercial B2B',
            'description' => 'Time de vendas B2B',
            'is_active' => false,
        ])->assertStatus(200);

        $this->patchJson('/api/crm/departments/'.$department['id'].'/toggle-active')
            ->assertStatus(200);

        $list = $this->getJson('/api/crm/departments')->assertStatus(200);
        $this->assertCount(1, $list->json('data'));

        $this->deleteJson('/api/crm/departments/'.$department['id'])->assertStatus(204);
    }
}
