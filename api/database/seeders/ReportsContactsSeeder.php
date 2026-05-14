<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds contact/company data for report testing.
 *
 * Covers: Contact CRM Report
 */
final class ReportsContactsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('Nenhum tenant encontrado. Execute DatabaseSeeder primeiro.');

            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedContacts($tenant->id);
        }
    }

    private function seedContacts(string $tenantId): void
    {
        // Create companies - usa firstOrCreate para garantir idempotência entre execuções
        // e unique()->company() no factory para evitar duplicatas na mesma execução
        $companies = [];
        $companyNames = [
            'Acme Corp', 'TechVision Ltd', 'GlobalSys Inc', 'Nexus Solutions',
            'PrimeLogic', 'AlphaOmega Enterprise', 'Quantum Dynamics', 'StellarWorks',
            'NovaTech Industries', 'Apex Digital',
        ];

        foreach ($companyNames as $companyName) {
            $companies[] = CRMCompany::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $companyName],
                [
                    'id' => (string) \Illuminate\Support\Str::orderedUuid(),
                    'document' => sprintf('%014d', random_int(10000000000, 99999999999)),
                    'email' => strtolower(str_replace(' ', '.', $companyName)).'@example.com',
                    'phone' => '+55'.random_int(10000000000, 99999999999),
                    'is_active' => true,
                ]
            );
        }

        // Create tags - idempotente usando firstOrCreate
        $tags = [];
        $tagDefinitions = [
            ['name' => 'Hot Lead', 'color' => '#FF5733'],
            ['name' => 'Cold Lead', 'color' => '#3498DB'],
            ['name' => 'VIP', 'color' => '#9B59B6'],
            ['name' => 'Newsletter', 'color' => '#27AE60'],
            ['name' => 'Follow-up', 'color' => '#F39C12'],
        ];

        foreach ($tagDefinitions as $tagDef) {
            $tags[] = CRMTag::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $tagDef['name']],
                [
                    'id' => (string) \Illuminate\Support\Str::orderedUuid(),
                    'color' => $tagDef['color'],
                    'is_active' => true,
                ]
            );
        }

        // Create 50 contacts - usa firstOrCreate com email único por tenant para idempotência
        $contacts = [];
        for ($i = 0; $i < min(50, 100); $i++) {
            $company = $companies[array_rand($companies)];
            $contactEmail = "contact{$i}.{$tenantId}@example.com";

            $contact = CRMContact::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'email' => $contactEmail],
                [
                    'id' => (string) \Illuminate\Support\Str::orderedUuid(),
                    'crm_company_id' => $company->id,
                    'name' => "Contact {$i}",
                    'phone' => '+55'.random_int(10000000000, 99999999999),
                    'is_active' => true,
                    'created_at' => now()->subDays(random_int(1, 60)),
                ]
            );
            $contacts[] = $contact;
        }

        // Assign random tags to contacts - usa syncWithoutDetaching que já é idempotente
        foreach ($contacts as $contact) {
            $randomTags = collect($tags)->random(random_int(0, 3));
            foreach ($randomTags as $tag) {
                $contact->tags()->syncWithoutDetaching([
                    $tag->id => [
                        'id' => (string) \Illuminate\Support\Str::orderedUuid(),
                        'tenant_id' => $tenantId,
                    ],
                ]);
            }
        }
    }
}
