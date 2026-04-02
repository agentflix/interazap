<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\CRM\Models\CRMDepartment;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;

class CRMDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();
        $desiredCount = 30;

        foreach ($tenants as $tenant) {
            $existingCount = CRMDepartment::query()
                ->where('tenant_id', $tenant->id)
                ->count();

            $missing = $desiredCount - $existingCount;
            if ($missing > 0) {
                CRMDepartment::factory()
                    ->count($missing)
                    ->create(['tenant_id' => $tenant->id]);
            }
        }

        $this->command->info(sprintf('Departments ensured per tenant: %d', $desiredCount));
    }
}
