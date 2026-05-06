<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMProduct;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CRMNegotiationProductsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    private function createNegotiation(string $tenantId): CRMNegotiation
    {
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'order' => 1,
        ]);

        return CRMNegotiation::factory()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
        ]);
    }

    public function test_create_item_and_updates_stock(): void
    {
        [$user, $tenantId] = $this->acting();
        $negotiation = $this->createNegotiation($tenantId);
        $product = CRMProduct::factory()->create([
            'tenant_id' => $tenantId,
            'track_stock' => true,
            'stock' => 5,
        ]);

        $payload = [
            'name' => 'Produto Teste',
            'quantity' => 2,
            'unit_price' => 100,
            'crm_product_id' => $product->id,
        ];

        $first = $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/products', $payload)
            ->assertStatus(201)
            ->json('data');

        $this->assertSame(2, $first['quantity']);
        $this->assertSame(3, $product->fresh()->stock);

        $second = $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/products', $payload)
            ->assertStatus(201)
            ->json('data');

        $this->assertSame(4, $second['quantity']);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_prevents_insufficient_stock(): void
    {
        [$user, $tenantId] = $this->acting();
        $negotiation = $this->createNegotiation($tenantId);
        $product = CRMProduct::factory()->create([
            'tenant_id' => $tenantId,
            'track_stock' => true,
            'stock' => 1,
        ]);

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/products', [
            'name' => 'Item',
            'quantity' => 2,
            'unit_price' => 50,
            'crm_product_id' => $product->id,
        ])->assertStatus(422);

        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_list_and_delete_item_restore_stock(): void
    {
        [$user, $tenantId] = $this->acting();
        $negotiation = $this->createNegotiation($tenantId);
        $product = CRMProduct::factory()->create([
            'tenant_id' => $tenantId,
            'track_stock' => true,
            'stock' => 3,
        ]);

        $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/products', [
            'name' => 'Item',
            'quantity' => 2,
            'unit_price' => 10,
            'crm_product_id' => $product->id,
        ])->assertStatus(201);

        $list = $this->getJson('/api/crm/negotiations/'.$negotiation->id.'/products')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $list);
        $itemId = $list[0]['id'];
        $this->assertSame(1, $product->fresh()->stock);

        $this->deleteJson('/api/crm/negotiation-products/'.$itemId)
            ->assertStatus(204);

        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_update_item_adjusts_stock_difference(): void
    {
        [$user, $tenantId] = $this->acting();
        $negotiation = $this->createNegotiation($tenantId);
        $product = CRMProduct::factory()->create([
            'tenant_id' => $tenantId,
            'track_stock' => true,
            'stock' => 10,
        ]);

        $itemId = $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/products', [
            'name' => 'Item',
            'quantity' => 2,
            'unit_price' => 20,
            'crm_product_id' => $product->id,
        ])->assertStatus(201)->json('data.id');

        $this->assertSame(8, $product->fresh()->stock);

        $updated = $this->putJson('/api/crm/negotiation-products/'.$itemId, [
            'name' => 'Item',
            'quantity' => 5,
            'unit_price' => 30,
            'crm_product_id' => $product->id,
        ])->assertStatus(200)->json('data');

        $this->assertSame(5, $updated['quantity']);
        $this->assertSame(5, $product->fresh()->stock);

        $this->putJson('/api/crm/negotiation-products/'.$itemId, [
            'name' => 'Item',
            'quantity' => 3,
            'unit_price' => 30,
            'crm_product_id' => $product->id,
        ])->assertStatus(200);

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_does_not_debit_stock_when_track_stock_disabled(): void
    {
        [$user, $tenantId] = $this->acting();
        $negotiation = $this->createNegotiation($tenantId);
        $product = CRMProduct::factory()->create([
            'tenant_id' => $tenantId,
            'track_stock' => false,
            'stock' => 5,
        ]);

        $payload = [
            'name' => 'Produto Infinito',
            'quantity' => 10,
            'unit_price' => 50,
            'crm_product_id' => $product->id,
        ];

        $response = $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/products', $payload)
            ->assertStatus(201)
            ->json('data');

        $this->assertSame(10, $response['quantity']);
        $this->assertSame(5, $product->fresh()->stock);

        $this->deleteJson('/api/crm/negotiation-products/'.$response['id'])
            ->assertStatus(204);

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_update_item_does_not_adjust_stock_when_track_stock_disabled(): void
    {
        [$user, $tenantId] = $this->acting();
        $negotiation = $this->createNegotiation($tenantId);
        $product = CRMProduct::factory()->create([
            'tenant_id' => $tenantId,
            'track_stock' => false,
            'stock' => 5,
        ]);

        $itemId = $this->postJson('/api/crm/negotiations/'.$negotiation->id.'/products', [
            'name' => 'Item',
            'quantity' => 3,
            'unit_price' => 20,
            'crm_product_id' => $product->id,
        ])->assertStatus(201)->json('data.id');

        $this->assertSame(5, $product->fresh()->stock);

        $this->putJson('/api/crm/negotiation-products/'.$itemId, [
            'name' => 'Item',
            'quantity' => 8,
            'unit_price' => 25,
            'crm_product_id' => $product->id,
        ])->assertStatus(200);

        $this->assertSame(5, $product->fresh()->stock);
    }
}
