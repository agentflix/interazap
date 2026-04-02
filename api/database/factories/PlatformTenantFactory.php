<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlatformTenant>
 */
class PlatformTenantFactory extends Factory
{
    protected $model = PlatformTenant::class;

    public function definition(): array
    {
        $plan = \Domain\Platform\Models\PlatformPlan::query()->where('is_active', true)->inRandomOrder()->first();

        if (! $plan) {
            $plan = \Domain\Platform\Models\PlatformPlan::factory()->create([
                'is_active' => true,
            ]);
        }

        $data = [
            'id' => (string) Str::orderedUuid(),
            'name' => $this->faker->company(),
            'tenant_code' => strtoupper(Str::random(8)),
            'primary_email' => $this->faker->safeEmail(),
            'plan_id' => $plan->id,
            'billing_webhook_token' => (string) Str::orderedUuid(),
            'is_active' => true,
        ];

        if (Schema::hasTable('platform_tenants') && Schema::hasColumn('platform_tenants', 'document')) {
            $data['document'] = $this->faker->numerify('###############');
        }

        if (Schema::hasTable('platform_tenants') && Schema::hasColumn('platform_tenants', 'asaas_customer_id')) {
            $data['asaas_customer_id'] = null;
        }

        return $data;
    }
}
