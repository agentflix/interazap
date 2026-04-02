<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMProduct;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
});

test('can create product', function (): void {
    $payload = [
        'name' => 'Produto X',
        'description' => 'Produto de teste',
        'type' => 'product',
        'price' => 199.90,
        'is_active' => true,
    ];

    $response = $this->postJson('/api/crm/products', $payload);

    $response->assertCreated()->assertJsonFragment(['name' => 'Produto X']);

    $this->assertDatabaseHas('crm_products', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Produto X',
    ]);
});

test('can list products', function (): void {
    CRMProduct::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/crm/products');

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('can show product', function (): void {
    $product = CRMProduct::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson("/api/crm/products/{$product->id}");

    $response->assertOk()->assertJsonFragment(['id' => $product->id]);
});

test('can update product', function (): void {
    $product = CRMProduct::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->putJson("/api/crm/products/{$product->id}", [
        'name' => 'Produto Atualizado',
        'price' => 299.90,
        'type' => 'service',
    ]);

    $response->assertOk()->assertJsonFragment(['name' => 'Produto Atualizado']);
});

test('can delete product', function (): void {
    $product = CRMProduct::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->deleteJson("/api/crm/products/{$product->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('crm_products', ['id' => $product->id]);
});
