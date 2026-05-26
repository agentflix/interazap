<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Services;

use Domain\Billing\Services\BillingGatewayService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingGatewayServiceTest extends TestCase
{
    private BillingGatewayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BillingGatewayService;
    }

    #[Test]
    public function test_create_payment_with_token_returns_payment_data_on_success(): void
    {
        Http::fake([
            '*/internal/billing/payments' => Http::response([
                'id' => 'pay_123456',
                'status' => 'CONFIRMED',
                'creditCard' => [
                    'creditCardBrand' => 'VISA',
                    'creditCardNumber' => '4814',
                ],
            ], 200),
        ]);

        $result = $this->service->createPaymentWithToken(
            customerId: 'cust_123',
            cardToken: 'token_abc',
            amount: 99.90,
            metadata: [
                'description' => 'Test subscription',
                'external_reference' => 'tenant-123',
            ]
        );

        $this->assertSame('pay_123456', $result['paymentId']);
        $this->assertSame('CONFIRMED', $result['status']);
        $this->assertSame('VISA', $result['brand']);
        $this->assertSame('4814', $result['last4']);
        $this->assertNull($this->service->getLastError());
    }

    #[Test]
    public function test_create_payment_with_token_returns_nulls_on_failure(): void
    {
        Http::fake([
            '*/internal/billing/payments' => Http::response([
                'errors' => ['Token inválido'],
            ], 422),
        ]);

        $result = $this->service->createPaymentWithToken(
            customerId: 'cust_123',
            cardToken: 'invalid_token',
            amount: 99.90,
        );

        $this->assertNull($result['paymentId']);
        $this->assertNull($result['status']);
        $this->assertNull($result['brand']);
        $this->assertNull($result['last4']);
        $this->assertNotNull($this->service->getLastError());
        $this->assertStringContainsString('422', $this->service->getLastError());
    }

    #[Test]
    public function test_get_payment_method_returns_card_data_on_success(): void
    {
        Http::fake([
            '*/internal/billing/payment-methods' => Http::response([
                'brand' => 'MASTERCARD',
                'last4' => '1234',
            ], 200),
        ]);

        $result = $this->service->getPaymentMethod('cust_123', 'token_xyz');

        $this->assertSame('MASTERCARD', $result['brand']);
        $this->assertSame('1234', $result['last4']);
        $this->assertNull($this->service->getLastError());
    }

    #[Test]
    public function test_get_payment_method_returns_nulls_on_failure(): void
    {
        Http::fake([
            '*/internal/billing/payment-methods' => Http::response(null, 500),
        ]);

        $result = $this->service->getPaymentMethod('cust_123', 'token_xyz');

        $this->assertNull($result['brand']);
        $this->assertNull($result['last4']);
        $this->assertNotNull($this->service->getLastError());
    }
}
