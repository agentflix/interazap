<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingAsaasWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rejects_when_token_invalid(): void
    {
        Config::set('services.asaas.webhook_token', 'hook-secret');

        $payload = [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_1',
                'status' => 'RECEIVED',
            ],
        ];

        $invalidToken = Str::uuid()->toString();

        $this->postJson("/api/webhooks/asaas/instances/{$invalidToken}", $payload, [
            'asaas-access-token' => 'hook-secret',
        ])->assertStatus(404);
    }

    public function test_processes_and_deduplicates_webhook(): void
    {
        Config::set('services.asaas.webhook_token', 'hook-secret');

        $tenant = PlatformTenant::factory()->create();

        $payload = [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_1',
                'status' => 'RECEIVED',
                'value' => 100.0,
                'externalReference' => 'inv_1',
            ],
        ];

        $url = '/api/webhooks/asaas/instances/'.$tenant->billing_webhook_token;

        $this->postJson($url, $payload, [
            'asaas-access-token' => 'hook-secret',
        ])->assertStatus(404);
    }
}
