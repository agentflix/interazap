<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMCustomField;
use Domain\CRM\Models\CRMCustomFieldValue;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CRMCustomFieldValueSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, array{street: string, district: string, city: string, state: string, cep: string}>
     */
    private const BRAZILIAN_ADDRESSES = [
        ['street' => 'Av. Paulista, 1578', 'district' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP', 'cep' => '01310-200'],
        ['street' => 'Rua da Bahia, 1148', 'district' => 'Centro', 'city' => 'Belo Horizonte', 'state' => 'MG', 'cep' => '30160-011'],
        ['street' => 'Av. Atlântica, 1702', 'district' => 'Copacabana', 'city' => 'Rio de Janeiro', 'state' => 'RJ', 'cep' => '22021-001'],
        ['street' => 'Rua Chile, 65', 'district' => 'Centro Histórico', 'city' => 'Salvador', 'state' => 'BA', 'cep' => '40020-000'],
        ['street' => 'Av. Beira Mar, 2450', 'district' => 'Meireles', 'city' => 'Fortaleza', 'state' => 'CE', 'cep' => '60165-121'],
        ['street' => 'Av. Ipiranga, 6681', 'district' => 'Partenon', 'city' => 'Porto Alegre', 'state' => 'RS', 'cep' => '90619-900'],
        ['street' => 'Rua XV de Novembro, 1299', 'district' => 'Centro', 'city' => 'Curitiba', 'state' => 'PR', 'cep' => '80020-310'],
        ['street' => 'Setor Bancário Sul, Quadra 2', 'district' => 'Asa Sul', 'city' => 'Brasília', 'state' => 'DF', 'cep' => '70070-120'],
        ['street' => 'Av. Djalma Batista, 1661', 'district' => 'Chapada', 'city' => 'Manaus', 'state' => 'AM', 'cep' => '69050-010'],
        ['street' => 'Rua João Pessoa, 267', 'district' => 'Cidade Alta', 'city' => 'Natal', 'state' => 'RN', 'cep' => '59025-500'],
        ['street' => 'Av. Boa Viagem, 5110', 'district' => 'Boa Viagem', 'city' => 'Recife', 'state' => 'PE', 'cep' => '51021-000'],
        ['street' => 'Av. Tancredo Neves, 999', 'district' => 'Caminho das Árvores', 'city' => 'Salvador', 'state' => 'BA', 'cep' => '41820-021'],
    ];

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        foreach ($tenants as $tenant) {
            $fields = CRMCustomField::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('entity', ['company', 'contact'])
                ->get()
                ->groupBy('entity')
                ->map(fn ($items) => $items->keyBy('name'));

            $companyFields = $fields->get('company');
            $contactFields = $fields->get('contact');
            if (! $companyFields) {
                continue;
            }
            if (! $contactFields) {
                continue;
            }

            $companies = CRMCompany::query()->where('tenant_id', $tenant->id)->get();
            $contacts = CRMContact::query()->where('tenant_id', $tenant->id)->get();

            foreach ($companies as $index => $company) {
                $address = self::BRAZILIAN_ADDRESSES[$index % count(self::BRAZILIAN_ADDRESSES)];

                $this->upsertValue($tenant->id, $companyFields->get('Endereço')?->id, 'company', (string) $company->id, $address['street']);
                $this->upsertValue($tenant->id, $companyFields->get('Bairro')?->id, 'company', (string) $company->id, $address['district']);
                $this->upsertValue($tenant->id, $companyFields->get('Cidade')?->id, 'company', (string) $company->id, $address['city']);
                $this->upsertValue($tenant->id, $companyFields->get('UF')?->id, 'company', (string) $company->id, $address['state']);
                $this->upsertValue($tenant->id, $companyFields->get('CEP')?->id, 'company', (string) $company->id, $address['cep']);
            }

            foreach ($contacts as $index => $contact) {
                $address = self::BRAZILIAN_ADDRESSES[($index + 3) % count(self::BRAZILIAN_ADDRESSES)];

                $this->upsertValue($tenant->id, $contactFields->get('Endereço')?->id, 'contact', (string) $contact->id, $address['street']);
                $this->upsertValue($tenant->id, $contactFields->get('Bairro')?->id, 'contact', (string) $contact->id, $address['district']);
                $this->upsertValue($tenant->id, $contactFields->get('Cidade')?->id, 'contact', (string) $contact->id, $address['city']);
                $this->upsertValue($tenant->id, $contactFields->get('UF')?->id, 'contact', (string) $contact->id, $address['state']);
                $this->upsertValue($tenant->id, $contactFields->get('CEP')?->id, 'contact', (string) $contact->id, $address['cep']);
            }
        }

        $this->command->info('CRM custom field values de endereço sincronizados com sucesso.');
    }

    private function upsertValue(string $tenantId, ?string $fieldId, string $entityType, string $entityId, string $value): void
    {
        if (! $fieldId) {
            return;
        }

        CRMCustomFieldValue::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'crm_custom_field_id' => $fieldId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'value' => $value,
            ]
        );
    }
}
