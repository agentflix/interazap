<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMCompaniesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_crud_company(): void
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        $create = $this->postJson('/api/crm/companies', [
            'name' => 'ACME',
            'document' => '11.222.333/0001-81',
            'email' => 'acme@example.com',
        ])->assertStatus(201)->json('data');

        $this->assertSame('ACME', $create['name']);

        $show = $this->getJson('/api/crm/companies/'.$create['id'])->assertStatus(200)->json('data');
        $this->assertSame('acme@example.com', $show['email']);

        $this->putJson('/api/crm/companies/'.$create['id'], [
            'name' => 'ACME Updated',
        ])->assertStatus(200);

        $list = $this->getJson('/api/crm/companies')->assertStatus(200);
        $this->assertCount(1, $list->json('data'));

        $this->deleteJson('/api/crm/companies/'.$create['id'])->assertStatus(204);
        $this->assertSame(0, \Domain\CRM\Models\CRMCompany::query()->count());
    }

    public function test_nome_e_unico_por_tenant(): void
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        $this->postJson('/api/crm/companies', [
            'name' => 'ACME',
        ])->assertStatus(201);

        $this->postJson('/api/crm/companies', [
            'name' => 'ACME',
        ])->assertStatus(422);
    }
}
