<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\CRM\Models\CRMCompany;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Garante que todo tenant possua uma empresa padrão ("Minha Empresa").
 */
final class CRMDefaultCompanySeeder extends Seeder
{
    /**
     * Executa o seeder.
     */
    public function run(): void
    {
        $created = 0;

        PlatformTenant::query()->chunk(100, function ($tenants) use (&$created): void {
            foreach ($tenants as $tenant) {
                $company = CRMCompany::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => 'Minha Empresa',
                    ],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'document' => $tenant->document,
                        'email' => $tenant->primary_email,
                        'phone' => null,
                        'is_active' => true,
                    ]
                );

                if ($company->wasRecentlyCreated) {
                    $created++;
                }
            }
        });

        $this->command->info("Default companies ensured: {$created}");
    }
}
