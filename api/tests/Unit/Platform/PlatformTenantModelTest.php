<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlatformTenantModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_booted_sets_id_and_billing_token(): void
    {
        $plan = PlatformPlan::factory()->create();

        $tenant = PlatformTenant::query()->create([
            'name' => 'Acme Inc',
            'tenant_code' => 'ACME1234',
            'primary_email' => 'acme@example.test',
            'plan_id' => $plan->id,
            'is_active' => true,
        ]);

        $this->assertNotEmpty($tenant->id);
        $this->assertNotEmpty($tenant->billing_webhook_token);
    }
}
