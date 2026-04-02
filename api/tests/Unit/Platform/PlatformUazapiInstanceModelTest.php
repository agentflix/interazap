<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Models\PlatformUazapiInstance;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlatformUazapiInstanceModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_booted_sets_id_and_casts_metadata_and_dates(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $now = now();

        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-500',
            'status' => 'connected',
            'metadata' => ['foo' => 'bar'],
            'last_status_at' => $now,
        ]);

        $this->assertNotEmpty($instance->id);
        $this->assertIsArray($instance->metadata);
        $this->assertInstanceOf(Carbon::class, $instance->last_status_at);
        $this->assertSame('bar', $instance->metadata['foo'] ?? null);
        $this->assertSame($tenant->id, $instance->tenant->id);
    }
}
