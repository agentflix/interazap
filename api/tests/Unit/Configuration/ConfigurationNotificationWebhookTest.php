<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Domain\Configuration\Models\ConfigurationNotificationWebhook;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConfigurationNotificationWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_webhook_model_casts_and_relationship(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $webhook = ConfigurationNotificationWebhook::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'CRM Webhook',
            'url' => 'https://example.test/webhook',
            'secret' => 'secret',
            'event_types' => ['new_ticket', 'ticket_closed'],
            'is_active' => true,
            'failure_count' => 1,
            'last_failure_at' => now(),
            'last_success_at' => now(),
        ]);

        $this->assertTrue($webhook->is_active);
        $this->assertSame(['new_ticket', 'ticket_closed'], $webhook->event_types);
        $this->assertSame($tenant->id, $webhook->tenant->id);
    }
}
