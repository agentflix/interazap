<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Platform\Http\Resources\PlatformUazapiInstanceResource;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Models\PlatformUazapiInstance;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PlatformUazapiInstanceResourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resource_transforms_instance_fields(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-12345678',
            'status' => 'connected',
            'webhook_url' => 'https://app.test/webhook',
            'config' => ['field_01' => 'a', 'field_02' => 'b'],
            'metadata' => ['connected' => true],
            'last_status_at' => now(),
        ]);

        $resource = new PlatformUazapiInstanceResource($instance);
        $data = $resource->toArray(new Request);

        $this->assertSame($instance->id, $data['id']);
        $this->assertSame($tenant->id, $data['tenant_id']);
        $this->assertSame('Instance', $data['name']);
        $this->assertSame('uazapi', $data['system_name']);
        // Token is no longer exposed for security reasons
        $this->assertTrue($data['has_token']);
        $this->assertSame('****5678', $data['token_preview']);
        $this->assertSame('connected', $data['status']);
        $this->assertSame('https://app.test/webhook', $data['webhook_url']);
        $this->assertSame(['field_01' => 'a', 'field_02' => 'b'], $data['config']);
        $this->assertSame(['connected' => true], $data['metadata']);
        $this->assertSame($instance->last_status_at?->format(DATE_ATOM), $data['last_status_at']);
        $this->assertSame($instance->last_status_at?->format(DATE_ATOM), $data['last_seen_at']);
        $this->assertSame($instance->created_at?->format(DATE_ATOM), $data['created_at']);
        $this->assertSame($instance->updated_at?->format(DATE_ATOM), $data['updated_at']);
    }
}
