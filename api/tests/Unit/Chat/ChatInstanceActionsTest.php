<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Actions\ChatInstanceActions;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Services\ChatChannelConnector;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ChatInstanceActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_requires_token_for_uazapi(): void
    {
        $connector = Mockery::mock(ChatChannelConnector::class);
        $actions = new ChatInstanceActions($connector);

        $this->expectException(ValidationException::class);

        $actions->create((string) Str::orderedUuid(), [
            'provider' => 'uazapi',
            'name' => 'Instance A',
        ]);
    }

    public function test_create_rejects_token_larger_than_column_limit(): void
    {
        $connector = Mockery::mock(ChatChannelConnector::class);
        $actions = new ChatInstanceActions($connector);
        $tenant = PlatformTenant::factory()->create();

        $this->expectException(ValidationException::class);

        $actions->create($tenant->id, [
            'provider' => 'uazapi',
            'name' => 'Instance Too Long',
            'token' => str_repeat('a', 256),
        ]);
    }

    public function test_create_rejects_build_log_payload_as_token(): void
    {
        $connector = Mockery::mock(ChatChannelConnector::class);
        $actions = new ChatInstanceActions($connector);
        $tenant = PlatformTenant::factory()->create();

        $this->expectException(ValidationException::class);

        $actions->create($tenant->id, [
            'provider' => 'uazapi',
            'name' => 'Instance Invalid Token',
            'token' => 'Application bundle generation failed. [1.145 seconds]',
        ]);
    }

    public function test_create_configures_webhook_and_persists_settings(): void
    {
        config()->set('services.channels.webhook_base_url', 'http://webhook.test');

        $connector = Mockery::mock(ChatChannelConnector::class);
        $connector->shouldReceive('configureWebhook')
            ->once()
            ->andReturn(['status' => 'ok']);

        $actions = new ChatInstanceActions($connector);

        $tenant = PlatformTenant::factory()->create();
        $instance = $actions->create($tenant->id, [
            'provider' => 'uazapi',
            'name' => 'Instance B',
            'token' => 'token-123',
            'settings' => ['mode' => 'production'],
        ]);

        $this->assertSame('uazapi', $instance->provider);
        $this->assertSame($tenant->id, $instance->tenant_id);
        $this->assertSame('disconnected', $instance->status);

        $settings = $instance->settings_json;
        $this->assertIsArray($settings);
        $this->assertSame('token-123', $settings['token'] ?? null);
        $this->assertSame('http://webhook.test/webhooks/uazapi/instances/'.$instance->webhook_token, $settings['webhook_url'] ?? null);
        $this->assertSame(['status' => 'ok'], $settings['webhook_response'] ?? null);
    }

    public function test_connect_updates_status_and_settings(): void
    {
        $connector = Mockery::mock(ChatChannelConnector::class);
        $connector->shouldReceive('connect')
            ->once()
            ->andReturn([
                'mode' => 'pair',
                'qr_code' => null,
                'pair_code' => '12345678',
                'expires_at' => now()->addMinutes(5)->toIso8601String(),
                'provider' => 'uazapi',
                'phone' => '5511999999999',
            ]);

        $actions = new ChatInstanceActions($connector);

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'uazapi',
            'settings_json' => ['token' => 'token-999'],
            'status' => 'disconnected',
        ]);

        $result = $actions->connect($tenant->id, $instance->id, [
            'mode' => 'pair',
            'phone' => '5511999999999',
        ]);

        $this->assertSame('pair', $result['connection']['mode']);
        $this->assertSame('connecting', $instance->fresh()->status);
        $this->assertSame('12345678', $instance->fresh()->settings_json['last_connection']['pair_code'] ?? null);
    }

    public function test_status_returns_fallback_payload(): void
    {
        $connector = Mockery::mock(ChatChannelConnector::class);
        $actions = new ChatInstanceActions($connector);

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'uazapi',
            'settings_json' => ['token' => 'token-abc'],
        ]);

        $result = $actions->status($tenant->id, $instance->id);

        $this->assertSame($instance->id, $result['instance']->id);
        $this->assertSame('qr', $result['status']['mode']);
        $this->assertStringStartsWith('data:application/json;base64,', $result['status']['qr_code']);
    }

    public function test_toggle_active_flips_flag(): void
    {
        $connector = Mockery::mock(ChatChannelConnector::class);
        $actions = new ChatInstanceActions($connector);

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $toggled = $actions->toggleActive($tenant->id, $instance->id);

        $this->assertFalse((bool) $toggled->is_active);
        $this->assertFalse((bool) $instance->fresh()->is_active);
    }

    public function test_update_merges_settings_and_refreshes_token(): void
    {
        $connector = Mockery::mock(ChatChannelConnector::class);
        $actions = new ChatInstanceActions($connector);

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Legacy Instance',
            'settings_json' => ['mode' => 'production', 'token' => 'old-token'],
            'webhook_token' => 'old-token',
        ]);

        $updated = $actions->update($tenant->id, $instance->id, [
            'name' => 'Updated Instance',
            'settings' => ['mode' => 'test'],
            'token' => 'new-token',
            'is_active' => false,
        ]);

        $this->assertSame('Updated Instance', $updated->name);
        $this->assertFalse((bool) $updated->is_active);
        $this->assertSame('test', $updated->settings_json['mode']);
        $this->assertSame('new-token', $updated->settings_json['token']);
        $this->assertSame('new-token', $updated->webhook_token);
    }
}
