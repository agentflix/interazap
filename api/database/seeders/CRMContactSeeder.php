<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMContactPhone;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CRMContactSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, string>
     */
    private const POSITIONS = [
        'Diretor Comercial',
        'Gerente de Compras',
        'Head de Operações',
        'CEO',
        'CFO',
        'Analista de Suprimentos',
        'Coordenador de TI',
        'Supervisor de Vendas',
        'Especialista de Projetos',
        'Gerente Administrativo',
    ];

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('Nenhum tenant encontrado. Execute DatabaseSeeder primeiro.');

            return;
        }

        $faker = fake('pt_BR');
        $created = 0;

        foreach ($tenants as $tenant) {
            $companies = CRMCompany::query()->where('tenant_id', $tenant->id)->get();

            if ($companies->isEmpty()) {
                $this->command->warn(sprintf('Tenant %s sem empresas. Execute CRMCompanySeeder.', $tenant->id));

                continue;
            }

            $companiesById = $companies->keyBy('id');

            for ($index = 1; $index <= 100; $index++) {
                $company = $companies->random();
                $fullName = $faker->firstName().' '.$faker->lastName().' '.$faker->lastName();

                $contact = CRMContact::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'email' => sprintf('contato%03d.%s@empresa.com.br', $index, strtolower((string) $tenant->tenant_code)),
                    ],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'crm_company_id' => $company->id,
                        'name' => $fullName,
                        'document' => $this->generateCpf($faker),
                        'phone' => $this->makeBrazilPhone($faker),
                        'whatsapp' => $this->makeBrazilPhone($faker),
                        'position' => self::POSITIONS[$index % count(self::POSITIONS)],
                        'is_active' => $index % 5 !== 0,
                    ]
                );

                $companyFromMap = $companiesById->get($contact->crm_company_id);
                if ($companyFromMap) {
                    $companyFromMap->contacts()->syncWithoutDetaching([
                        $contact->id => [
                            'id' => (string) Str::orderedUuid(),
                            'tenant_id' => $tenant->id,
                        ],
                    ]);
                }

                CRMContactPhone::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'crm_contact_id' => $contact->id,
                        'label' => 'mobile',
                    ],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'phone_e164' => $this->makeBrazilPhone($faker, true),
                        'is_primary' => true,
                        'valid_from' => now()->subDays(random_int(1, 90)),
                        'valid_to' => null,
                    ]
                );

                if ($index % 3 === 0) {
                    CRMContactPhone::query()->firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'crm_contact_id' => $contact->id,
                            'label' => 'work',
                        ],
                        [
                            'id' => (string) Str::orderedUuid(),
                            'phone_e164' => $this->makeBrazilPhone($faker, true),
                            'is_primary' => false,
                            'valid_from' => now()->subDays(random_int(20, 180)),
                            'valid_to' => null,
                        ]
                    );
                }

                $created++;
            }
        }

        $this->command->info(sprintf('Contacts ensured: %d', $created));
    }

    private function makeBrazilPhone(\Faker\Generator $faker, bool $unique = false): string
    {
        $ddd = (string) $faker->randomElement(['11', '21', '31', '41', '47', '48', '51', '61', '62', '71', '81', '85']);
        $digits = $unique
            ? $faker->unique()->numerify('9########')
            : $faker->numerify('9########');

        return '+55'.$ddd.$digits;
    }

    private function generateCpf(\Faker\Generator $faker): string
    {
        // @phpstan-ignore-next-line
        return (string) $faker->unique()->cpf(false);
    }
}
