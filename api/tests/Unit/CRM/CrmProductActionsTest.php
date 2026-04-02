<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use Domain\CRM\Actions\CRMProductActions;
use Domain\CRM\DTOs\CRMProductDTO;
use Domain\CRM\Models\CRMProduct;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @group crm
 * @group product
 */
class CRMProductActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CRMProductActions $actions;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actions = new CRMProductActions;
        $tenant = PlatformTenant::factory()->create();
        $this->tenantId = $tenant->id;
    }

    public function test_list_returns_paginated_products(): void
    {
        CRMProduct::factory()->count(5)->create(['tenant_id' => $this->tenantId]);
        $otherTenant = PlatformTenant::factory()->create();
        CRMProduct::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

        $result = $this->actions->list($this->tenantId);

        expect($result->total())->toBe(5);
    }

    public function test_create_product_with_valid_data(): void
    {
        $dto = new CRMProductDTO(
            name: 'Produto Teste',
            price: 100.50,
            description: 'Descrição do produto',
            is_active: true
        );

        $product = $this->actions->create($this->tenantId, $dto);

        expect($product)
            ->toBeInstanceOf(CRMProduct::class)
            ->tenant_id->toBe($this->tenantId)
            ->name->toBe('Produto Teste')
            ->price->toEqual(100.50)
            ->is_active->toBeTrue();

        $this->assertDatabaseHas('crm_products', [
            'id' => $product->id,
            'tenant_id' => $this->tenantId,
            'name' => 'Produto Teste',
        ]);
    }

    public function test_create_throws_exception_for_duplicate_name(): void
    {
        $dto = new CRMProductDTO(
            name: 'Produto Duplicado',
            price: 100,
            description: 'Desc',
            is_active: true
        );

        $this->actions->create($this->tenantId, $dto);

        expect(fn (): \Domain\CRM\Models\CRMProduct => $this->actions->create($this->tenantId, $dto))
            ->toThrow(ValidationException::class, 'Produto já cadastrado');
    }

    public function test_create_allows_duplicate_name_across_tenants(): void
    {
        $otherTenant = PlatformTenant::factory()->create();
        $dto = new CRMProductDTO(
            name: 'Produto Comum',
            price: 100,
            description: 'Desc',
            is_active: true
        );

        $productA = $this->actions->create($this->tenantId, $dto);
        $productB = $this->actions->create($otherTenant->id, $dto);

        expect($productA->name)->toBe('Produto Comum');
        expect($productB->name)->toBe('Produto Comum');
        expect($productA->tenant_id)->not->toBe($productB->tenant_id);
    }

    public function test_update_product(): void
    {
        $product = CRMProduct::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Nome Antigo',
            'price' => 50.00,
        ]);

        $dto = new CRMProductDTO(
            name: 'Nome Novo',
            price: 75.00,
            description: 'Nova descrição',
            is_active: false
        );

        $updated = $this->actions->update($this->tenantId, $product->id, $dto);

        expect($updated->name)->toBe('Nome Novo');
        expect($updated->price)->toEqual(75.00);
        expect($updated->is_active)->toBeFalse();
    }

    public function test_update_throws_exception_if_new_name_exists(): void
    {
        CRMProduct::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Produto Existente',
        ]);

        $product = CRMProduct::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Produto A',
        ]);

        $dto = new CRMProductDTO(
            name: 'Produto Existente',
            price: 100,
            description: 'Desc',
            is_active: true
        );

        expect(fn (): \Domain\CRM\Models\CRMProduct => $this->actions->update($this->tenantId, $product->id, $dto))
            ->toThrow(ValidationException::class);
    }

    public function test_update_allows_same_name(): void
    {
        $product = CRMProduct::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Produto Original',
            'price' => 100,
        ]);

        $dto = new CRMProductDTO(
            name: 'Produto Original',
            price: 150,
            description: 'Nova descrição',
            is_active: true
        );

        $updated = $this->actions->update($this->tenantId, $product->id, $dto);

        expect($updated->price)->toEqual(150.0);
    }

    public function test_delete_removes_product(): void
    {
        $product = CRMProduct::factory()->create(['tenant_id' => $this->tenantId]);

        $this->actions->delete($this->tenantId, $product->id);

        $this->assertDatabaseMissing('crm_products', [
            'id' => $product->id,
        ]);
    }

    public function test_delete_enforces_tenant_isolation(): void
    {
        $otherTenant = PlatformTenant::factory()->create();
        $product = CRMProduct::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->actions->delete($this->tenantId, $product->id);
    }

    public function test_find_returns_product(): void
    {
        $product = CRMProduct::factory()->create(['tenant_id' => $this->tenantId]);

        $found = $this->actions->find($this->tenantId, $product->id);

        expect($found->id)->toBe($product->id);
    }

    public function test_find_enforces_tenant_isolation(): void
    {
        $otherTenant = PlatformTenant::factory()->create();
        $product = CRMProduct::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->actions->find($this->tenantId, $product->id);
    }
}
