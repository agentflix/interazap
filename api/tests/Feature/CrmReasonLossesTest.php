<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMReasonLossesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_crud_reason_loss(): void
    {
        [$user, $tenantId] = $this->acting();

        $reason = $this->postJson('/api/crm/reason-losses', [
            'name' => 'Preço alto',
        ])->assertStatus(201)->json('data');

        $this->putJson('/api/crm/reason-losses/'.$reason['id'], [
            'name' => 'Sem orçamento',
        ])->assertStatus(200);

        $list = $this->getJson('/api/crm/reason-losses')->assertStatus(200);
        $this->assertCount(1, $list->json('data'));
    }

    public function test_create_reason_loss_with_all_fields(): void
    {
        [$user, $tenantId] = $this->acting();

        $response = $this->postJson('/api/crm/reason-losses', [
            'name' => 'Preço alto',
            'description' => 'Cliente achou o preço muito elevado',
            'requires_comment' => true,
            'is_active' => true,
        ])->assertStatus(201);

        $data = $response->json('data');
        $this->assertEquals('Preço alto', $data['name']);
        $this->assertEquals('Cliente achou o preço muito elevado', $data['description']);
        $this->assertTrue($data['requires_comment']);
        $this->assertTrue($data['is_active']);
    }

    public function test_update_reason_loss_description(): void
    {
        [$user, $tenantId] = $this->acting();

        $reason = $this->postJson('/api/crm/reason-losses', [
            'name' => 'Preço alto',
            'description' => 'Descrição inicial',
        ])->assertStatus(201)->json('data');

        $updated = $this->putJson('/api/crm/reason-losses/'.$reason['id'], [
            'name' => 'Preço alto',
            'description' => 'Descrição atualizada',
            'is_active' => false,
        ])->assertStatus(200)->json('data');

        $this->assertEquals('Descrição atualizada', $updated['description']);
        $this->assertFalse($updated['is_active']);
    }

    public function test_cannot_create_duplicate_reason_loss(): void
    {
        [$user, $tenantId] = $this->acting();

        $this->postJson('/api/crm/reason-losses', [
            'name' => 'Preço alto',
        ])->assertStatus(201);

        $this->postJson('/api/crm/reason-losses', [
            'name' => 'Preço alto',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_toggle_is_active_status(): void
    {
        [$user, $tenantId] = $this->acting();

        $reason = $this->postJson('/api/crm/reason-losses', [
            'name' => 'Preço alto',
            'is_active' => true,
        ])->assertStatus(201)->json('data');

        $updated = $this->putJson('/api/crm/reason-losses/'.$reason['id'], [
            'name' => 'Preço alto',
            'is_active' => false,
        ])->assertStatus(200)->json('data');

        $this->assertFalse($updated['is_active']);
    }

    public function test_list_only_active_reason_losses(): void
    {
        [$user, $tenantId] = $this->acting();

        $this->postJson('/api/crm/reason-losses', [
            'name' => 'Ativo',
            'is_active' => true,
        ])->assertStatus(201);

        $this->postJson('/api/crm/reason-losses', [
            'name' => 'Inativo',
            'is_active' => false,
        ])->assertStatus(201);

        $list = $this->getJson('/api/crm/reason-losses?active=true')->assertStatus(200);
        $this->assertCount(1, $list->json('data'));
    }
}
