<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMProductsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_crud_produto(): void
    {
        [$user, $tenantId] = $this->acting();

        $payload = [
            'name' => 'Produto A',
            'code' => 'PROD-A-001',
            'price' => 199.9,
            'cost' => 120.3,
            'unit' => 'un',
            'type' => 'product',
            'description' => 'Descricao',
            'stock_quantity' => 10,
            'min_stock' => 2,
            'is_featured' => true,
        ];

        $created = $this->postJson('/api/crm/products', $payload)
            ->assertStatus(201)
            ->json('data');

        $this->assertSame('Produto A', $created['name']);
        $this->assertSame('PROD-A-001', $created['code']);
        $this->assertEquals(120.3, $created['cost']);
        $this->assertSame('un', $created['unit']);
        $this->assertSame(10, $created['stock_quantity']);
        $this->assertSame(2, $created['min_stock']);
        $this->assertTrue($created['is_featured']);
        $this->assertSame(10, $created['stock']);
        $this->assertTrue($created['track_stock']);

        $this->putJson('/api/crm/products/'.$created['id'], [
            'name' => 'Produto B',
            'price' => 250,
            'type' => 'service',
            'description' => null,
            'is_active' => false,
            'code' => 'SRV-B-001',
            'cost' => 0,
            'unit' => 'h',
            'stock_quantity' => 0,
            'min_stock' => 0,
            'is_featured' => false,
            'track_stock' => true,
            'stock' => 5,
        ])->assertStatus(200)
            ->assertJsonPath('data.track_stock', false)
            ->assertJsonPath('data.stock', 0)
            ->assertJsonPath('data.code', 'SRV-B-001')
            ->assertJsonPath('data.unit', 'h');

        $list = $this->getJson('/api/crm/products')->assertStatus(200);
        $this->assertCount(1, $list->json('data'));

        $filtered = $this->getJson('/api/crm/products?search=SRV-B-001')->assertStatus(200);
        $this->assertCount(1, $filtered->json('data'));

        $statusFilteredInactive = $this->getJson('/api/crm/products?search=inativo')->assertStatus(200);
        $this->assertCount(1, $statusFilteredInactive->json('data'));

        $statusFilteredActive = $this->getJson('/api/crm/products?search=ativo')->assertStatus(200);
        $this->assertCount(0, $statusFilteredActive->json('data'));

        $this->postJson('/api/crm/products', [
            'name' => 'Produto B',
            'price' => 250,
            'type' => 'service',
        ])->assertStatus(422);
    }
}
