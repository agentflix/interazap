<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingGatewayServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.gateway.url', 'http://gateway.test');
        config()->set('services.gateway.api_key', 'gateway-key');
        config()->set('services.gateway.timeout', 1);
    }

    public function test_ensure_customer_returns_existing_id(): void
    {
        $tenant = PlatformTenant::factory()->create([
            'asaas_customer_id' => 'cust-existing',
        ]);

        $service = new BillingGatewayService;

        $customerId = $service->ensureCustomer($tenant);

        $this->assertSame('cust-existing', $customerId);
    }

    public function test_ensure_customer_returns_null_when_document_missing(): void
    {
        $tenant = PlatformTenant::factory()->create([
            'document' => null,
            'asaas_customer_id' => null,
        ]);

        $service = new BillingGatewayService;

        $customerId = $service->ensureCustomer($tenant);

        $this->assertNull($customerId);
        $tenant->refresh();
        $this->assertNull($tenant->asaas_customer_id);
    }

    public function test_ensure_customer_creates_and_persists_id(): void
    {
        Http::fake([
            'http://gateway.test/internal/billing/customers' => Http::response(['id' => 'cust-123'], 200),
        ]);

        $tenant = PlatformTenant::factory()->create([
            'document' => '12345678901',
            'asaas_customer_id' => null,
        ]);

        $service = new BillingGatewayService;

        $customerId = $service->ensureCustomer($tenant);

        $this->assertSame('cust-123', $customerId);
        $tenant->refresh();
        $this->assertSame('cust-123', $tenant->asaas_customer_id);
    }

    public function test_create_payment_returns_nulls_on_failure(): void
    {
        Http::fake([
            'http://gateway.test/internal/billing/payments' => Http::response(['error' => 'fail'], 500),
        ]);

        $service = new BillingGatewayService;
        $result = $service->createPayment('cust-1', 10.0, '2026-01-10', 'Invoice', 'ext-1', 'PIX');

        $this->assertSame(['id' => null, 'invoiceUrl' => null, 'status' => null], $result);
        $this->assertNotNull($service->getLastError());
    }

    public function test_create_payment_returns_values_on_success(): void
    {
        Http::fake([
            'http://gateway.test/internal/billing/payments' => Http::response([
                'id' => 'pay-1',
                'invoiceUrl' => 'http://asaas.test/invoice/1',
                'status' => 'PENDING',
            ], 200),
        ]);

        $service = new BillingGatewayService;
        $result = $service->createPayment('cust-1', 10.0, '2026-01-10', 'Invoice', 'ext-1', 'PIX');

        $this->assertSame('pay-1', $result['id']);
        $this->assertSame('http://asaas.test/invoice/1', $result['invoiceUrl']);
        $this->assertSame('PENDING', $result['status']);
        $this->assertNull($service->getLastError());
    }

    public function test_get_pix_qrcode_returns_payload(): void
    {
        Http::fake([
            'http://gateway.test/internal/billing/payments/pay_1/pix' => Http::response([
                'payload' => 'pix-payload',
                'encodedImage' => 'pix-image',
            ], 200),
        ]);

        $service = new BillingGatewayService;
        $result = $service->getPixQRCode('pay_1');

        $this->assertSame('pix-payload', $result['payload']);
        $this->assertSame('pix-image', $result['encodedImage']);
    }

    public function test_get_pix_qrcode_returns_nulls_on_failure(): void
    {
        Http::fake([
            'http://gateway.test/internal/billing/payments/pay_2/pix' => Http::response([], 500),
        ]);

        $service = new BillingGatewayService;
        $result = $service->getPixQRCode('pay_2');

        $this->assertNull($result['payload']);
        $this->assertNull($result['encodedImage']);
    }

    public function test_get_payment_status_returns_values(): void
    {
        Http::fake([
            'http://gateway.test/internal/billing/payments/pay_3/status' => Http::response([
                'status' => 'CONFIRMED',
                'value' => 99.9,
                'confirmedDate' => '2026-01-20',
            ], 200),
        ]);

        $service = new BillingGatewayService;
        $result = $service->getPaymentStatus('pay_3');

        $this->assertSame('CONFIRMED', $result['status']);
        $this->assertSame(99.9, $result['value']);
        $this->assertSame('2026-01-20', $result['confirmedDate']);
    }

    public function test_get_payment_status_returns_nulls_on_failure(): void
    {
        Http::fake([
            'http://gateway.test/internal/billing/payments/pay_4/status' => Http::response([], 500),
        ]);

        $service = new BillingGatewayService;
        $result = $service->getPaymentStatus('pay_4');

        $this->assertNull($result['status']);
        $this->assertNull($result['value']);
        $this->assertNull($result['confirmedDate']);
    }

    public function test_create_product_returns_id(): void
    {
        Http::fake([
            'http://gateway.test/internal/platform/products' => Http::response(['id' => 'prod-1'], 200),
        ]);

        $plan = PlatformPlan::factory()->create([
            'name' => 'Starter',
            'price_monthly' => 19.9,
        ]);

        $service = new BillingGatewayService;
        $productId = $service->createProduct($plan);

        $this->assertSame('prod-1', $productId);
    }

    public function test_update_product_returns_true_on_success(): void
    {
        Http::fake([
            'http://gateway.test/internal/platform/products/prod-1' => Http::response([], 200),
        ]);

        $plan = PlatformPlan::factory()->create([
            'name' => 'Starter',
            'price_monthly' => 19.9,
            'asaas_product_id' => 'prod-1',
        ]);

        $service = new BillingGatewayService;
        $result = $service->updateProduct($plan);

        $this->assertTrue($result);
    }

    public function test_create_payment_with_token_returns_payment_data(): void
    {
        Http::fake([
            'http://gateway.test/internal/billing/payments' => Http::response([
                'id' => 'pay-token-1',
                'status' => 'CONFIRMED',
                'creditCard' => [
                    'creditCardBrand' => 'VISA',
                    'creditCardNumber' => '4242',
                ],
            ], 200),
        ]);

        $service = new BillingGatewayService;
        $result = $service->createPaymentWithToken(
            customerId: 'cust-1',
            cardToken: 'token-123',
            amount: 99.9,
            metadata: ['description' => 'Test', 'external_reference' => 'ext-1']
        );

        $this->assertSame('pay-token-1', $result['paymentId']);
        $this->assertSame('CONFIRMED', $result['status']);
        $this->assertSame('VISA', $result['brand']);
        $this->assertSame('4242', $result['last4']);
        $this->assertNull($service->getLastError());
    }
}
