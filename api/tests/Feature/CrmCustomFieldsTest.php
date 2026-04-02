<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMCustomFieldsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_crud_custom_field(): void
    {
        [$user, $tenantId] = $this->acting();

        $field = $this->postJson('/api/crm/custom-fields', [
            'name' => 'Fonte',
            'type' => 'text',
            'entity' => 'contact',
            'is_required' => true,
        ])->assertStatus(201)->json('data');

        $this->putJson('/api/crm/custom-fields/'.$field['id'], [
            'name' => 'Origem',
            'type' => 'text',
            'entity' => 'contact',
        ])->assertStatus(200);

        $list = $this->getJson('/api/crm/custom-fields')->assertStatus(200);
        $this->assertCount(1, $list->json('data'));

        $this->deleteJson('/api/crm/custom-fields/'.$field['id'])->assertStatus(204);
    }
}
