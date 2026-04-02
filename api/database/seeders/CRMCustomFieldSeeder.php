<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\CRM\Models\CRMCustomField;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CRMCustomFieldSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, array{name: string, type: string, entity: string, is_required: bool, options: array<int, string>|null}>
     */
    private const FIELDS = [
        ['name' => 'Endereço', 'type' => 'text', 'entity' => 'company', 'is_required' => false, 'options' => null],
        ['name' => 'Bairro', 'type' => 'text', 'entity' => 'company', 'is_required' => false, 'options' => null],
        ['name' => 'Cidade', 'type' => 'text', 'entity' => 'company', 'is_required' => false, 'options' => null],
        ['name' => 'UF', 'type' => 'select', 'entity' => 'company', 'is_required' => false, 'options' => ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO']],
        ['name' => 'CEP', 'type' => 'text', 'entity' => 'company', 'is_required' => false, 'options' => null],

        ['name' => 'Endereço', 'type' => 'text', 'entity' => 'contact', 'is_required' => false, 'options' => null],
        ['name' => 'Bairro', 'type' => 'text', 'entity' => 'contact', 'is_required' => false, 'options' => null],
        ['name' => 'Cidade', 'type' => 'text', 'entity' => 'contact', 'is_required' => false, 'options' => null],
        ['name' => 'UF', 'type' => 'select', 'entity' => 'contact', 'is_required' => false, 'options' => ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO']],
        ['name' => 'CEP', 'type' => 'text', 'entity' => 'contact', 'is_required' => false, 'options' => null],
    ];

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        foreach ($tenants as $tenant) {
            foreach (self::FIELDS as $field) {
                $customField = CRMCustomField::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $field['name'],
                        'entity' => $field['entity'],
                    ],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'type' => $field['type'],
                        'is_required' => $field['is_required'],
                        'options' => $field['options'],
                    ]
                );

                $customField->fill([
                    'type' => $field['type'],
                    'is_required' => $field['is_required'],
                    'options' => $field['options'],
                ]);
                $customField->save();
            }
        }

        $this->command->info(sprintf('CRM custom fields ensured per tenant: %d', count(self::FIELDS)));
    }
}
