<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BillingAsaasWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_asaas_webhook_endpoint_is_not_exposed_in_api_service(): void
    {
        $payload = [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_1',
                'status' => 'RECEIVED',
            ],
        ];

        $this->postJson('/api/webhooks/asaas/instances/any-token', $payload, [
            'asaas-access-token' => 'hook-secret',
        ])->assertStatus(404);
    }
}
