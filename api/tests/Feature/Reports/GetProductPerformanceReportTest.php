<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMProduct;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class GetProductPerformanceReportTest extends \Tests\Feature\Reports\ReportsTestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = AuthUser::factory()->create();
        $this->tenantId = (string) $this->user->tenant_id;
        $this->user->givePermissionTo('reports.crm.view');
        Sanctum::actingAs($this->user, abilities: ['*']);
    }

    public function test_returns_product_performance(): void
    {
        $product = CRMProduct::factory()->create(['tenant_id' => $this->tenantId, 'name' => 'Widget A']);
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);
        $neg = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'status' => 'won',
        ]);

        DB::table('crm_negotiation_products')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenantId,
            'crm_negotiation_id' => $neg->id,
            'crm_product_id' => $product->id,
            'name' => 'Widget A',
            'quantity' => 5,
            'unit_price' => 100.00,
            'total' => 500.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/product-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $products = $response->json('data.data.products');
        $this->assertNotEmpty($products);
        $this->assertSame('Widget A', $products[0]['product_name']);
        $this->assertSame(5, $products[0]['sold_qty']);
    }

    public function test_returns_pipeline_data(): void
    {
        $product = CRMProduct::factory()->create(['tenant_id' => $this->tenantId]);
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenantId]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);
        $neg = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenantId,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'status' => 'open',
        ]);

        DB::table('crm_negotiation_products')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenantId,
            'crm_negotiation_id' => $neg->id,
            'crm_product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 3,
            'unit_price' => 50.00,
            'total' => 150.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/product-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $products = $response->json('data.data.products');
        $this->assertSame(3, $products[0]['pipeline_qty']);
    }

    public function test_tenant_isolation(): void
    {
        $otherUser = AuthUser::factory()->create();
        $product = CRMProduct::factory()->create(['tenant_id' => $otherUser->tenant_id]);
        $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $otherUser->tenant_id]);
        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $otherUser->tenant_id,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);
        $neg = CRMNegotiation::factory()->create([
            'tenant_id' => $otherUser->tenant_id,
            'crm_negotiation_funnel_id' => $funnel->id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'status' => 'won',
        ]);

        DB::table('crm_negotiation_products')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $otherUser->tenant_id,
            'crm_negotiation_id' => $neg->id,
            'crm_product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 10,
            'unit_price' => 100.00,
            'total' => 1000.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/product-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.data.products'));
    }

    public function test_returns_empty_when_no_data(): void
    {
        $response = $this->getJson('/api/reports/product-performance?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.data.products'));
        $this->assertEmpty($response->json('data.data.proposals'));
    }
}
