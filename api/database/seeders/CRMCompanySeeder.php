<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\CRM\Models\CRMCompany;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CRMCompanySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, string>
     */
    private const COMPANY_PREFIXES = [
        'Alvorada',
        'Vértice',
        'Aurora',
        'Sigma',
        'Nexus',
        'Prime',
        'Horizonte',
        'Eixo',
        'Atlas',
        'Pulsar',
        'Bravus',
        'Lume',
        'Raiz',
        'Conecta',
        'Sólida',
        'Integra',
        'Matriz',
    ];

    /**
     * @var array<int, string>
     */
    private const COMPANY_SUFFIXES = [
        'Tecnologia',
        'Comércio',
        'Distribuição',
        'Logística',
        'Alimentos',
        'Saúde',
        'Serviços',
        'Educação',
        'Digital',
        'Indústria',
        'Consultoria',
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
            for ($index = 1; $index <= min(30, 100); $index++) {
                $prefix = self::COMPANY_PREFIXES[$index % count(self::COMPANY_PREFIXES)];
                $suffix = self::COMPANY_SUFFIXES[($index + 3) % count(self::COMPANY_SUFFIXES)];
                $companyName = sprintf('%s %s %02d', $prefix, $suffix, $index);
                $slug = Str::slug($companyName);

                CRMCompany::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $companyName,
                    ],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'document' => $this->generateCnpj(),
                        'email' => sprintf('contato+%s@%s.com.br', $tenant->tenant_code, $slug),
                        'phone' => $this->makeBrazilPhone(),
                    ]
                );

                $created++;
            }

            for ($extra = 0; $extra < 8; $extra++) {
                $name = sprintf('%s %s LTDA', $faker->company(), strtoupper((string) $faker->lexify('???')));

                CRMCompany::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $name,
                    ],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'document' => $this->generateCnpj(),
                        'email' => sprintf('vendas@%s.com.br', Str::slug($name)),
                        'phone' => $this->makeBrazilPhone(),
                    ]
                );

                $created++;
            }
        }

        $this->command->info(sprintf('Companies ensured: %d', $created));
    }

    private function makeBrazilPhone(): string
    {
        $ddd = (string) fake()->randomElement(['11', '21', '31', '41', '47', '48', '51', '61', '62', '71', '81', '85']);

        return '+55'.$ddd.'9'.fake()->numerify('########');
    }

    private function generateCnpj(): string
    {
        $base = '';

        for ($index = 0; $index < 12; $index++) {
            $base .= (string) random_int(0, 9);
        }

        $digit1 = $this->cnpjDigit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $digit2 = $this->cnpjDigit($base.$digit1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $base.$digit1.$digit2;
    }

    /**
     * @param  array<int, int>  $weights
     */
    private function cnpjDigit(string $numbers, array $weights): int
    {
        $sum = 0;

        foreach (str_split($numbers) as $index => $number) {
            $sum += ((int) $number) * $weights[$index];
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
