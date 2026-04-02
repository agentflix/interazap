<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMProduct;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMProductsAllTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_list_all_products_active(): void
    {
        [$user, $tenantId] = $this->acting();
        CRMProduct::factory()->create(['tenant_id' => $tenantId, 'is_active' => true]);
        CRMProduct::factory()->create(['tenant_id' => $tenantId, 'is_active' => false]);

        $list = $this->getJson('/api/crm/products-all')->assertStatus(200)->json('data');
        $this->assertCount(1, $list);
    }
}
