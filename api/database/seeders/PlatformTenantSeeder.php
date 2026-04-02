<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlatformTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            [
                'name' => 'Acme Logistics',
                'tenant_code' => 'ACMELOG',
                'primary_email' => 'admin@acmelogistics.test',
                'document' => '12345678000190',
            ],
            [
                'name' => 'Beta Retail',
                'tenant_code' => 'BETARTL',
                'primary_email' => 'admin@betaretail.test',
                'document' => '98765432000110',
            ],
            [
                'name' => 'Nova Services',
                'tenant_code' => 'NOVASVC',
                'primary_email' => 'admin@novaservices.test',
                'document' => '11223344000155',
            ],
        ];

        $plans = \Domain\Platform\Models\PlatformPlan::query()->get();

        foreach ($tenants as $tenant) {
            PlatformTenant::query()->firstOrCreate(
                ['tenant_code' => $tenant['tenant_code']],
                [
                    'id' => (string) Str::orderedUuid(),
                    'name' => $tenant['name'],
                    'tenant_code' => $tenant['tenant_code'],
                    'primary_email' => $tenant['primary_email'],
                    'document' => $tenant['document'],
                    'plan_id' => $plans->isNotEmpty() ? $plans->random()->id : null,
                    'billing_webhook_token' => (string) Str::uuid(),
                    'is_active' => true,
                ]
            );
        }

        $this->command->info(sprintf('Extra tenants created: %d', count($tenants)));
    }
}
