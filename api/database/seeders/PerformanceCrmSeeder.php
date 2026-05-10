<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;

/**
 * Performance seed for CRM context.
 *
 * Seeds all 26 CRM tables per tenant using raw inserts in FK order.
 * Total: ~47,500 records across 50 tenants.
 */
final class PerformanceCrmSeeder
{
    use WithoutModelEvents;

    private const int BATCH_SIZE = 1000;

    public function seedForTenant(string $tenantId): void
    {
        // 1. Base entities (no FK deps)
        $companyIds = $this->seedCompanies($tenantId);
        $tagIds = $this->seedTags($tenantId);
        $productIds = $this->seedProducts($tenantId);
        $reasonLossIds = $this->seedReasonLosses($tenantId);
        $this->seedDepartments($tenantId);

        // 2. Contacts (depend on companies)
        $contactIds = $this->seedContacts($tenantId, $companyIds);
        $this->seedContactPhones($tenantId, $contactIds);
        $this->seedCompanyContacts($tenantId, $companyIds, $contactIds);
        $this->seedContactTags($tenantId, $contactIds, $tagIds);
        $this->seedCompanyTags($tenantId, $companyIds, $tagIds);

        // 3. Funnel entities
        [$funnelIds, $stepIds] = $this->seedFunnels($tenantId);

        // 4. Negotiations (depend on contacts, funnels, steps, users, reasons)
        $userIds = DB::table('auth_users')->where('tenant_id', $tenantId)->pluck('id')->toArray();
        $negotiationIds = $this->seedNegotiations($tenantId, $companyIds, $contactIds, $funnelIds, $stepIds, $userIds, $reasonLossIds);
        $this->seedNegotiationTasks($tenantId, $negotiationIds, $userIds);
        $this->seedNegotiationProducts($tenantId, $negotiationIds, $productIds);
        $this->seedNegotiationTags($tenantId, $negotiationIds, $tagIds);

        // 5. Proposals
        $proposalIds = $this->seedProposals($tenantId, $negotiationIds);
        $this->seedProposalItems($tenantId, $proposalIds, $productIds);

        // 6. Custom fields
        $customFieldIds = $this->seedCustomFields($tenantId);
        $this->seedCustomFieldValues($tenantId, $customFieldIds, $companyIds, $contactIds, $negotiationIds);

        // 7. Notes (morph)
        $this->seedNotes($tenantId, $companyIds, $contactIds, $negotiationIds, $userIds);
        $this->seedNegotiationFiles($tenantId, $negotiationIds, $userIds);

        // 8. Events
        $eventIds = $this->seedEvents($tenantId, $userIds);
        $this->seedEventLinks($tenantId, $eventIds, $negotiationIds, $contactIds);
        $this->seedEventParticipants($tenantId, $eventIds, $userIds, $contactIds);
        $this->seedEventReminders($tenantId, $eventIds, $userIds);
    }

    /** @return array<int, string> */
    private function seedCompanies(string $tenantId): array
    {
        $faker = fake('pt_BR');
        $states = ['SP', 'RJ', 'MG', 'RS', 'PR', 'SC', 'BA', 'PE', 'CE', 'GO'];
        $companies = [];
        $ids = [];
        $count = random_int(15, 25);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $companies[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'name' => $faker->company(),
                'document' => sprintf('%014d', random_int(10000000000, 99999999999)),
                'email' => 'company.'.random_int(1000, 9999).'@perf.local',
                'phone' => '+55'.random_int(1100000000, 99999999999),
                'address' => $faker->streetAddress(),
                'city' => $faker->city(),
                'state' => $states[array_rand($states)],
                'zip_code' => $faker->postcode(),
                'is_active' => random_int(0, 100) > 10,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
                'deleted_at' => random_int(0, 100) > 95 ? now()->subDays(random_int(1, 30)) : null,
            ];
        }

        PerformanceSeeder::insertBatch('crm_companies', $companies, self::BATCH_SIZE);

        return $ids;
    }

    /** @return array<int, string> */
    private function seedContacts(string $tenantId, array $companyIds): array
    {
        $faker = fake('pt_BR');
        $contacts = [];
        $ids = [];
        $count = random_int(40, 60);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $hasCompany = $companyIds !== [] && (bool) random_int(0, 1);

            $contacts[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'crm_company_id' => $hasCompany ? $companyIds[array_rand($companyIds)] : null,
                'name' => $faker->name(),
                'email' => 'contact.'.random_int(1000, 9999).".{$tenantId}@perf.local",
                'document' => sprintf('%011d', random_int(100000000, 999999999)),
                'phone' => '+55'.random_int(1100000000, 99999999999),
                'whatsapp' => (bool) random_int(0, 1) ? '+55'.random_int(1100000000, 99999999999) : null,
                'position' => ['Gerente', 'Diretor', 'Analista', 'Coordenador', 'Vendedor'][array_rand(['Gerente', 'Diretor', 'Analista', 'Coordenador', 'Vendedor'])],
                'avatar_url' => null,
                'notes' => (bool) random_int(0, 1) ? 'Notas de contato '.random_int(1, 100) : null,
                'custom_fields' => null,
                'is_active' => random_int(0, 100) > 10,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
                'deleted_at' => random_int(0, 100) > 95 ? now()->subDays(random_int(1, 30)) : null,
            ];
        }

        PerformanceSeeder::insertBatch('crm_contacts', $contacts, self::BATCH_SIZE);

        return $ids;
    }

    private function seedContactPhones(string $tenantId, array $contactIds): void
    {
        $labels = ['Celular', 'Trabalho', 'Casa', 'WhatsApp'];
        $phones = [];

        foreach ($contactIds as $contactId) {
            $phoneCount = random_int(1, 3);
            for ($p = 0; $p < $phoneCount; $p++) {
                $phones[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tenant_id' => $tenantId,
                    'crm_contact_id' => $contactId,
                    'label' => $labels[array_rand($labels)],
                    'phone_e164' => '+55'.random_int(1100000000, 99999999999),
                    'is_primary' => $p === 0,
                    'valid_from' => PerformanceSeeder::randomDate(),
                    'valid_to' => random_int(0, 100) > 90 ? now()->addDays(random_int(30, 365)) : null,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        PerformanceSeeder::insertBatch('crm_contact_phones', $phones, self::BATCH_SIZE);
    }

    private function seedCompanyContacts(string $tenantId, array $companyIds, array $contactIds): void
    {
        $links = [];
        $usedPairs = [];
        $count = min(count($companyIds) * 3, count($contactIds));

        for ($i = 0; $i < $count; $i++) {
            $companyId = $companyIds[array_rand($companyIds)];
            $contactId = $contactIds[array_rand($contactIds)];
            $pair = "{$companyId}:{$contactId}";

            if (isset($usedPairs[$pair])) {
                continue;
            }
            $usedPairs[$pair] = true;

            $links[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'crm_company_id' => $companyId,
                'crm_contact_id' => $contactId,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('crm_company_contacts', $links, self::BATCH_SIZE);
    }

    /** @return array<int, string> */
    private function seedTags(string $tenantId): array
    {
        $categories = ['status', 'prioridade', 'segmento', 'origem'];
        $colors = ['#16a34a', '#dc2626', '#2563eb', '#f97316', '#7c3aed', '#db2777', '#0891b2', '#65a30d'];
        $tagNames = ['VIP', 'Quente', 'Frio', 'Inadimplente', 'Onboarding', 'Risco de Churn', 'Upsell', 'Novo', 'Recorrente', 'Referência', 'Parceiro', 'Lead Qualificado'];

        $tags = [];
        $ids = [];
        $count = random_int(12, 18);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $tags[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'name' => $tagNames[$i % count($tagNames)].' '.random_int(1, 99),
                'color' => $colors[array_rand($colors)],
                'category' => $categories[array_rand($categories)],
                'is_active' => random_int(0, 100) > 10,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('crm_tags', $tags, self::BATCH_SIZE);

        return $ids;
    }

    private function seedContactTags(string $tenantId, array $contactIds, array $tagIds): void
    {
        $links = [];
        $usedPairs = [];
        $count = min(count($contactIds) * 2, 100);

        for ($i = 0; $i < $count; $i++) {
            $contactId = $contactIds[array_rand($contactIds)];
            $tagId = $tagIds[array_rand($tagIds)];
            $pair = "{$contactId}:{$tagId}";

            if (isset($usedPairs[$pair])) {
                continue;
            }
            $usedPairs[$pair] = true;

            $links[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'crm_contact_id' => $contactId,
                'crm_tag_id' => $tagId,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('crm_contact_tags', $links, self::BATCH_SIZE);
    }

    private function seedCompanyTags(string $tenantId, array $companyIds, array $tagIds): void
    {
        $links = [];
        $usedPairs = [];
        $count = min(count($companyIds) * 2, 50);

        for ($i = 0; $i < $count; $i++) {
            $companyId = $companyIds[array_rand($companyIds)];
            $tagId = $tagIds[array_rand($tagIds)];
            $pair = "{$companyId}:{$tagId}";

            if (isset($usedPairs[$pair])) {
                continue;
            }
            $usedPairs[$pair] = true;

            $links[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'crm_company_id' => $companyId,
                'crm_tag_id' => $tagId,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('crm_company_tags', $links, self::BATCH_SIZE);
    }

    /** @return array<int, string> */
    private function seedProducts(string $tenantId): array
    {
        $types = ['product', 'service'];
        $units = ['un', 'kg', 'hr', 'm', 'm2'];
        $products = [];
        $ids = [];
        $count = random_int(15, 25);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $type = $types[array_rand($types)];
            $price = random_int(50, 5000) + (random_int(0, 99) / 100);
            $cost = $price * (random_int(30, 70) / 100);

            $products[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'name' => 'Produto '.random_int(100, 999),
                'description' => 'Descrição do produto '.random_int(1, 100),
                'type' => $type,
                'price' => $price,
                'cost' => $cost,
                'code' => 'SKU-'.random_int(1000, 9999),
                'unit' => $units[array_rand($units)],
                'stock_quantity' => random_int(0, 1000),
                'min_stock' => random_int(5, 50),
                'is_featured' => random_int(0, 100) > 80,
                'is_active' => random_int(0, 100) > 10,
                'stock' => random_int(0, 1000),
                'track_stock' => (bool) random_int(0, 1),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('crm_products', $products, self::BATCH_SIZE);

        return $ids;
    }

    /** @return array<int, string> */
    private function seedReasonLosses(string $tenantId): array
    {
        $reasons = [
            'Preço alto', 'Concorrência', 'Falta de interesse', 'Produto não atende',
            'Prazo de entrega', 'Sem budget', 'Decisão adiada', 'Mudanca de prioridade',
            'Contato perdido', 'Cliente desistiu',
        ];
        $reasonLosses = [];
        $ids = [];
        $count = random_int(5, 10);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $reasonLosses[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'name' => $reasons[$i % count($reasons)],
                'description' => 'Motivo: '.$reasons[$i % count($reasons)],
                'requires_comment' => (bool) random_int(0, 1),
                'is_active' => random_int(0, 100) > 10,
                'position' => $i,
                'usage_count' => random_int(0, 50),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('crm_reason_losses', $reasonLosses, self::BATCH_SIZE);

        return $ids;
    }

    /** @return array<int, string> */
    private function seedDepartments(string $tenantId): array
    {
        $names = ['Comercial', 'Suporte', 'Financeiro', 'Marketing', 'TI', 'RH', 'Operacoes', 'Logistica', 'Juridico', 'Administrativo'];
        $departments = [];
        $ids = [];
        $count = random_int(3, 8);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $departments[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'name' => $names[$i % count($names)],
                'description' => 'Departamento de '.$names[$i % count($names)],
                'is_active' => random_int(0, 100) > 10,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('crm_departments', $departments, self::BATCH_SIZE);

        return $ids;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function seedFunnels(string $tenantId): array
    {
        $funnelNames = ['Vendas', 'Suporte', 'Onboarding', 'Recuperacao', 'Upsell'];
        $stepConfigs = [
            ['Prospect', 'Qualificacao', 'Proposta', 'Negociacao', 'Fechamento'],
            ['Novo', 'Triagem', 'Resolucao', 'Escalonamento', 'Fechado'],
            ['Cadastro', 'Configuracao', 'Treinamento', 'Go-live', 'Acompanhamento'],
            ['Identificacao', 'Contato', 'Oferta', 'Conversao', 'Retencao'],
            ['Oportunidade', 'Apresentacao', 'Proposta', 'Aprovacao', 'Fechamento'],
        ];

        $funnelIds = [];
        $stepIds = [];
        $funnelCount = random_int(2, 4);

        for ($f = 0; $f < $funnelCount; $f++) {
            $funnelId = PerformanceSeeder::uuid();
            $funnelIds[] = $funnelId;

            $funnels = [
                [
                    'id' => $funnelId,
                    'tenant_id' => $tenantId,
                    'name' => $funnelNames[$f % count($funnelNames)],
                    'description' => 'Funil de '.$funnelNames[$f % count($funnelNames)],
                    'is_active' => random_int(0, 100) > 10,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ],
            ];
            PerformanceSeeder::insertBatch('crm_negotiation_funnels', $funnels, self::BATCH_SIZE);

            $steps = $stepConfigs[$f % count($stepConfigs)];
            foreach ($steps as $s => $stepName) {
                $stepId = PerformanceSeeder::uuid();
                $stepIds[] = $stepId;

                $stepData = [
                    [
                        'id' => $stepId,
                        'tenant_id' => $tenantId,
                        'crm_negotiation_funnel_id' => $funnelId,
                        'name' => $stepName,
                        'color' => ['#16a34a', '#2563eb', '#f97316', '#dc2626', '#7c3aed'][$s],
                        'is_active' => true,
                        'order' => $s,
                        'created_at' => PerformanceSeeder::randomDate(),
                        'updated_at' => now(),
                    ],
                ];
                PerformanceSeeder::insertBatch('crm_negotiation_funnel_steps', $stepData, self::BATCH_SIZE);
            }
        }

        return [$funnelIds, $stepIds];
    }

    /** @return array<int, string> */
    private function seedNegotiations(
        string $tenantId,
        array $companyIds,
        array $contactIds,
        array $funnelIds,
        array $stepIds,
        array $userIds,
        array $reasonLossIds,
    ): array {
        $statusWeights = ['open' => 40, 'won' => 25, 'lost' => 20, 'paused' => 15];
        $negotiations = [];
        $ids = [];
        $count = random_int(40, 60);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $status = PerformanceSeeder::weightedRandom($statusWeights);
            $hasCompany = $companyIds !== [] && (bool) random_int(0, 1);
            $closedAt = $status !== 'open' ? now()->subDays(random_int(1, 90)) : null;

            $negotiations[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'crm_company_id' => $hasCompany ? $companyIds[array_rand($companyIds)] : null,
                'crm_contact_id' => $contactIds[array_rand($contactIds)] ?? null,
                'crm_negotiation_funnel_id' => $funnelIds[array_rand($funnelIds)] ?? null,
                'crm_negotiation_funnel_step_id' => $stepIds[array_rand($stepIds)] ?? null,
                'crm_reason_loss_id' => $status === 'lost' && $reasonLossIds !== [] ? $reasonLossIds[array_rand($reasonLossIds)] : null,
                'auth_user_id' => $userIds === [] ? null : $userIds[array_rand($userIds)],
                'title' => 'Negociacao '.random_int(1000, 9999),
                'amount' => random_int(1000, 50000) + (random_int(0, 99) / 100),
                'status' => $status,
                'lead_score' => random_int(0, 100),
                'position' => $i,
                'expected_close' => now()->addDays(random_int(-30, 90)),
                'closed_at' => $closedAt,
                'notes' => (bool) random_int(0, 1) ? 'Notas da negociacao '.random_int(1, 100) : null,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
                'deleted_at' => random_int(0, 100) > 95 ? now()->subDays(random_int(1, 30)) : null,
            ];
        }

        PerformanceSeeder::insertBatch('crm_negotiations', $negotiations, self::BATCH_SIZE);

        return $ids;
    }

    private function seedNegotiationTasks(string $tenantId, array $negotiationIds, array $userIds): void
    {
        $statusWeights = ['pending' => 40, 'done' => 35, 'overdue' => 25];
        $tasks = [];

        foreach ($negotiationIds as $negotiationId) {
            $taskCount = random_int(0, 3);
            for ($t = 0; $t < $taskCount; $t++) {
                $status = PerformanceSeeder::weightedRandom($statusWeights);
                $dueDate = match ($status) {
                    'overdue' => now()->subDays(random_int(1, 30)),
                    'done' => now()->subDays(random_int(1, 15)),
                    default => now()->addDays(random_int(1, 30)),
                };

                $tasks[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tenant_id' => $tenantId,
                    'crm_negotiation_id' => $negotiationId,
                    'auth_user_id' => $userIds === [] ? null : $userIds[array_rand($userIds)],
                    'title' => 'Tarefa '.random_int(100, 999),
                    'description' => 'Descricao da tarefa '.random_int(1, 100),
                    'due_date' => $dueDate,
                    'status' => $status,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($tasks !== []) {
            PerformanceSeeder::insertBatch('crm_negotiation_tasks', $tasks, self::BATCH_SIZE);
        }
    }

    private function seedNegotiationProducts(string $tenantId, array $negotiationIds, array $productIds): void
    {
        $links = [];

        foreach ($negotiationIds as $negotiationId) {
            $productCount = random_int(0, 3);
            for ($p = 0; $p < $productCount; $p++) {
                $quantity = random_int(1, 10);
                $unitPrice = random_int(100, 5000) + (random_int(0, 99) / 100);

                $links[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tenant_id' => $tenantId,
                    'crm_negotiation_id' => $negotiationId,
                    'crm_product_id' => $productIds[array_rand($productIds)] ?? null,
                    'name' => 'Produto Negociacao '.random_int(100, 999),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $quantity * $unitPrice,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($links !== []) {
            PerformanceSeeder::insertBatch('crm_negotiation_products', $links, self::BATCH_SIZE);
        }
    }

    private function seedNegotiationTags(string $tenantId, array $negotiationIds, array $tagIds): void
    {
        $links = [];
        $usedPairs = [];
        $count = min(count($negotiationIds) * 2, 50);

        for ($i = 0; $i < $count; $i++) {
            $negotiationId = $negotiationIds[array_rand($negotiationIds)];
            $tagId = $tagIds[array_rand($tagIds)];
            $pair = "{$negotiationId}:{$tagId}";

            if (isset($usedPairs[$pair])) {
                continue;
            }
            $usedPairs[$pair] = true;

            $links[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'crm_negotiation_id' => $negotiationId,
                'crm_tag_id' => $tagId,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('crm_negotiation_tags', $links, self::BATCH_SIZE);
    }

    /** @return array<int, string> */
    private function seedProposals(string $tenantId, array $negotiationIds): array
    {
        $statusWeights = ['draft' => 20, 'sent' => 30, 'viewed' => 15, 'accepted' => 20, 'rejected' => 15];
        $proposals = [];
        $ids = [];

        foreach ($negotiationIds as $negotiationId) {
            if (random_int(0, 100) > 40) { // 60% of negotiations have proposals
                $proposalCount = random_int(1, 2);
                for ($p = 0; $p < $proposalCount; $p++) {
                    $status = PerformanceSeeder::weightedRandom($statusWeights);
                    $id = PerformanceSeeder::uuid();
                    $ids[] = $id;

                    $proposals[] = [
                        'id' => $id,
                        'tenant_id' => $tenantId,
                        'crm_negotiation_id' => $negotiationId,
                        'title' => 'Proposta '.random_int(1000, 9999),
                        'number' => random_int(1000, 9999),
                        'total' => random_int(1000, 50000) + (random_int(0, 99) / 100),
                        'status' => $status,
                        'valid_until' => now()->addDays(random_int(7, 60)),
                        'public_token' => (string) \Illuminate\Support\Str::uuid(),
                        'notes' => (bool) random_int(0, 1) ? 'Observacoes da proposta' : null,
                        'sent_at' => in_array($status, ['sent', 'viewed', 'accepted', 'rejected']) ? now()->subDays(random_int(1, 30)) : null,
                        'viewed_at' => in_array($status, ['viewed', 'accepted', 'rejected']) ? now()->subDays(random_int(1, 20)) : null,
                        'accepted_at' => $status === 'accepted' ? now()->subDays(random_int(1, 10)) : null,
                        'rejected_at' => $status === 'rejected' ? now()->subDays(random_int(1, 10)) : null,
                        'created_at' => PerformanceSeeder::randomDate(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if ($proposals !== []) {
            PerformanceSeeder::insertBatch('crm_proposals', $proposals, self::BATCH_SIZE);
        }

        return $ids;
    }

    private function seedProposalItems(string $tenantId, array $proposalIds, array $productIds): void
    {
        $items = [];

        foreach ($proposalIds as $proposalId) {
            $itemCount = random_int(1, 4);
            for ($i = 0; $i < $itemCount; $i++) {
                $quantity = random_int(1, 5);
                $unitPrice = random_int(100, 5000) + (random_int(0, 99) / 100);
                $discount = random_int(0, 20) / 100 * $unitPrice;

                $items[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tenant_id' => $tenantId,
                    'crm_proposal_id' => $proposalId,
                    'crm_product_id' => $productIds[array_rand($productIds)] ?? null,
                    'name' => 'Item '.random_int(100, 999),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'total' => ($quantity * $unitPrice) - $discount,
                    'position' => $i,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($items !== []) {
            PerformanceSeeder::insertBatch('crm_proposal_items', $items, self::BATCH_SIZE);
        }
    }

    /** @return array<int, string> */
    private function seedCustomFields(string $tenantId): array
    {
        $types = ['text', 'number', 'date', 'select', 'multiselect'];
        $entities = ['company', 'contact', 'negotiation'];
        $fields = [];
        $ids = [];
        $count = random_int(5, 15);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $type = $types[array_rand($types)];

            $fields[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'name' => 'Campo '.random_int(100, 999),
                'type' => $type,
                'entity' => $entities[array_rand($entities)],
                'options' => in_array($type, ['select', 'multiselect']) ? json_encode(['Opcao A', 'Opcao B', 'Opcao C']) : null,
                'is_required' => (bool) random_int(0, 1),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('crm_custom_fields', $fields, self::BATCH_SIZE);

        return $ids;
    }

    private function seedCustomFieldValues(
        string $tenantId,
        array $customFieldIds,
        array $companyIds,
        array $contactIds,
        array $negotiationIds,
    ): void {
        $values = [];
        $count = min(count($customFieldIds) * 3, 100);

        for ($i = 0; $i < $count; $i++) {
            $fieldId = $customFieldIds[array_rand($customFieldIds)];
            $entityType = ['company', 'contact', 'negotiation'][array_rand(['company', 'contact', 'negotiation'])];
            $entityId = match ($entityType) {
                'company' => $companyIds[array_rand($companyIds)] ?? null,
                'contact' => $contactIds[array_rand($contactIds)] ?? null,
                'negotiation' => $negotiationIds[array_rand($negotiationIds)] ?? null,
            };

            if ($entityId === null) {
                continue;
            }

            $values[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'crm_custom_field_id' => $fieldId,
                'entity_type' => 'Domain\\CRM\\Models\\CRM'.ucfirst($entityType),
                'entity_id' => $entityId,
                'value' => 'Valor '.random_int(1, 1000),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        if ($values !== []) {
            PerformanceSeeder::insertBatch('crm_custom_field_values', $values, self::BATCH_SIZE);
        }
    }

    private function seedNotes(
        string $tenantId,
        array $companyIds,
        array $contactIds,
        array $negotiationIds,
        array $userIds,
    ): void {
        $notes = [];
        $count = random_int(20, 40);
        $entities = [];

        foreach ($companyIds as $id) {
            $entities[] = ['type' => \Domain\CRM\Models\CRMCompany::class, 'id' => $id];
        }
        foreach ($contactIds as $id) {
            $entities[] = ['type' => \Domain\CRM\Models\CRMContact::class, 'id' => $id];
        }
        foreach ($negotiationIds as $id) {
            $entities[] = ['type' => \Domain\CRM\Models\CRMNegotiation::class, 'id' => $id];
        }

        for ($i = 0; $i < $count; $i++) {
            $entity = $entities[array_rand($entities)];
            $notes[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'entity_type' => $entity['type'],
                'entity_id' => $entity['id'],
                'auth_user_id' => $userIds === [] ? null : $userIds[array_rand($userIds)],
                'content' => 'Nota de acompanhamento '.random_int(1, 1000).'. '.fake('pt_BR')->sentence(),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('crm_notes', $notes, self::BATCH_SIZE);
    }

    private function seedNegotiationFiles(string $tenantId, array $negotiationIds, array $userIds): void
    {
        $mimeTypes = ['application/pdf', 'image/png', 'image/jpeg', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $files = [];
        $count = min(count($negotiationIds), 10);

        for ($i = 0; $i < $count; $i++) {
            $files[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'crm_negotiation_id' => $negotiationIds[array_rand($negotiationIds)],
                'auth_user_id' => $userIds === [] ? null : $userIds[array_rand($userIds)],
                'name' => 'arquivo_'.random_int(1000, 9999).['.pdf', '.png', '.jpg', '.xlsx'][array_rand(['.pdf', '.png', '.jpg', '.xlsx'])],
                'path' => 'uploads/'.random_int(2024, 2026).'/'.random_int(1, 12).'/'.$tenantId.'/'.random_int(1000, 9999).'.bin',
                'size' => random_int(1000, 10_000_000),
                'mime_type' => $mimeTypes[array_rand($mimeTypes)],
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('crm_negotiation_files', $files, self::BATCH_SIZE);
    }

    /** @return array<int, string> */
    private function seedEvents(string $tenantId, array $userIds): array
    {
        $types = ['meeting', 'call', 'task', 'reminder'];
        $statuses = ['scheduled' => 50, 'completed' => 30, 'cancelled' => 20];
        $recurrences = ['none', 'daily', 'weekly', 'monthly'];
        $colors = ['#16a34a', '#2563eb', '#f97316', '#dc2626', '#7c3aed', '#db2777'];
        $events = [];
        $ids = [];
        $count = random_int(15, 25);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $type = $types[array_rand($types)];
            $status = PerformanceSeeder::weightedRandom($statuses);
            $startsAt = now()->subDays(random_int(-30, 90))->setTime(random_int(8, 18), random_int(0, 59));
            $isAllDay = random_int(0, 100) > 90;
            $hasRecurrence = random_int(0, 100) > 80;

            $events[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'auth_user_id' => $userIds === [] ? null : $userIds[array_rand($userIds)],
                'title' => ucfirst($type).' com '.fake('pt_BR')->name(),
                'type' => $type,
                'status' => $status,
                'description' => (bool) random_int(0, 1) ? 'Descricao do evento '.random_int(1, 100) : null,
                'location' => (bool) random_int(0, 1) ? (random_int(0, 1) !== 0 ? 'Sala '.random_int(100, 999) : 'https://meet.perf.local/'.random_int(1000, 9999)) : null,
                'starts_at' => $startsAt,
                'ends_at' => $isAllDay ? null : $startsAt->copy()->addMinutes(random_int(15, 180)),
                'is_all_day' => $isAllDay,
                'recurrence' => $hasRecurrence ? $recurrences[array_rand($recurrences)] : 'none',
                'recurrence_ends_at' => $hasRecurrence ? $startsAt->copy()->addMonths(random_int(1, 6)) : null,
                'color' => $colors[array_rand($colors)],
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
                'deleted_at' => random_int(0, 100) > 95 ? now()->subDays(random_int(1, 30)) : null,
            ];
        }

        PerformanceSeeder::insertBatch('crm_events', $events, self::BATCH_SIZE);

        return $ids;
    }

    private function seedEventLinks(string $tenantId, array $eventIds, array $negotiationIds, array $contactIds): void
    {
        $links = [];
        $count = min(count($eventIds) * 2, 30);

        $entities = [];
        foreach ($negotiationIds as $id) {
            $entities[] = ['type' => \Domain\CRM\Models\CRMNegotiation::class, 'id' => $id];
        }
        foreach ($contactIds as $id) {
            $entities[] = ['type' => \Domain\CRM\Models\CRMContact::class, 'id' => $id];
        }

        for ($i = 0; $i < $count; $i++) {
            $entity = $entities[array_rand($entities)];
            $links[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'crm_event_id' => $eventIds[array_rand($eventIds)],
                'linkable_type' => $entity['type'],
                'linkable_id' => $entity['id'],
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('crm_event_links', $links, self::BATCH_SIZE);
    }

    private function seedEventParticipants(string $tenantId, array $eventIds, array $userIds, array $contactIds): void
    {
        $statuses = ['pending' => 30, 'accepted' => 50, 'declined' => 20];
        $participants = [];

        foreach ($eventIds as $eventId) {
            $participantCount = random_int(1, 4);
            for ($p = 0; $p < $participantCount; $p++) {
                $isOrganizer = $p === 0;
                $isUser = (bool) random_int(0, 1);

                $participants[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tenant_id' => $tenantId,
                    'crm_event_id' => $eventId,
                    'auth_user_id' => $isUser && $userIds !== [] ? $userIds[array_rand($userIds)] : null,
                    'crm_contact_id' => ! $isUser && $contactIds !== [] ? $contactIds[array_rand($contactIds)] : null,
                    'name' => fake('pt_BR')->name(),
                    'email' => 'participant.'.random_int(1000, 9999).'@perf.local',
                    'status' => PerformanceSeeder::weightedRandom($statuses),
                    'is_organizer' => $isOrganizer,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        PerformanceSeeder::insertBatch('crm_event_participants', $participants, self::BATCH_SIZE);
    }

    private function seedEventReminders(string $tenantId, array $eventIds, array $userIds): void
    {
        $reminders = [];

        foreach ($eventIds as $eventId) {
            if (random_int(0, 100) > 50) { // 50% of events have reminders
                $reminderCount = random_int(1, 2);
                for ($r = 0; $r < $reminderCount; $r++) {
                    $isSent = (bool) random_int(0, 1);

                    $reminders[] = [
                        'id' => PerformanceSeeder::uuid(),
                        'tenant_id' => $tenantId,
                        'crm_event_id' => $eventId,
                        'auth_user_id' => $userIds === [] ? null : $userIds[array_rand($userIds)],
                        'type' => ['notification', 'email', 'sms'][array_rand(['notification', 'email', 'sms'])],
                        'minutes_before' => [0, 5, 15, 30, 60, 1440][array_rand([0, 5, 15, 30, 60, 1440])],
                        'notify_ui' => (bool) random_int(0, 1),
                        'notify_email' => (bool) random_int(0, 1),
                        'notify_push' => (bool) random_int(0, 1),
                        'notify_whatsapp' => (bool) random_int(0, 1),
                        'notify_webhook' => (bool) random_int(0, 1),
                        'scheduled_at' => now()->subDays(random_int(1, 30)),
                        'is_sent' => $isSent,
                        'sent_at' => $isSent ? now()->subDays(random_int(1, 30)) : null,
                        'created_at' => PerformanceSeeder::randomDate(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if ($reminders !== []) {
            PerformanceSeeder::insertBatch('crm_event_reminders', $reminders, self::BATCH_SIZE);
        }
    }
}
