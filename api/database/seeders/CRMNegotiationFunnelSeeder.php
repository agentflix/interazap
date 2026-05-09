<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CRMNegotiationFunnelSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, array{name: string, is_active: bool, steps: array<int, array{name: string, color: string, order: int, is_active: bool}>}>
     */
    private const FUNNELS = [
        [
            'name' => 'Vendas Padrão',
            'is_active' => true,
            'steps' => [
                ['name' => 'Novo Lead', 'color' => '#3B82F6', 'is_active' => true, 'order' => 1],
                ['name' => 'Qualificação', 'color' => '#8B5CF6', 'is_active' => true, 'order' => 2],
                ['name' => 'Proposta Enviada', 'color' => '#F59E0B', 'is_active' => true, 'order' => 3],
                ['name' => 'Negociação', 'color' => '#EF4444', 'is_active' => true, 'order' => 4],
                ['name' => 'Fechado - Ganho', 'color' => '#10B981', 'is_active' => true, 'order' => 5],
                ['name' => 'Perdido', 'color' => '#6B7280', 'is_active' => true, 'order' => 6],
            ],
        ],
        [
            'name' => 'Onboarding de Clientes',
            'is_active' => true,
            'steps' => [
                ['name' => 'Cadastro Inicial', 'color' => '#3B82F6', 'is_active' => true, 'order' => 1],
                ['name' => 'Treinamento Agendado', 'color' => '#8B5CF6', 'is_active' => true, 'order' => 2],
                ['name' => 'Em Treinamento', 'color' => '#F59E0B', 'is_active' => true, 'order' => 3],
                ['name' => 'Onboarding Concluído', 'color' => '#10B981', 'is_active' => true, 'order' => 4],
            ],
        ],
        [
            'name' => 'Suporte Técnico',
            'is_active' => true,
            'steps' => [
                ['name' => 'Ticket Aberto', 'color' => '#3B82F6', 'is_active' => true, 'order' => 1],
                ['name' => 'Em Análise', 'color' => '#8B5CF6', 'is_active' => true, 'order' => 2],
                ['name' => 'Em Atendimento', 'color' => '#F59E0B', 'is_active' => true, 'order' => 3],
                ['name' => 'Aguardando Cliente', 'color' => '#EF4444', 'is_active' => true, 'order' => 4],
                ['name' => 'Resolvido', 'color' => '#10B981', 'is_active' => true, 'order' => 5],
            ],
        ],
    ];

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        foreach ($tenants as $tenant) {
            foreach (self::FUNNELS as $funnelTemplate) {
                $funnel = CRMNegotiationFunnel::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $funnelTemplate['name'],
                    ],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'is_active' => $funnelTemplate['is_active'],
                    ]
                );

                $funnel->is_active = $funnelTemplate['is_active'];
                $funnel->save();

                foreach ($funnelTemplate['steps'] as $stepTemplate) {
                    // Check by unique constraint fields (tenant + funnel + order)
                    $existingByOrder = CRMNegotiationFunnelStep::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('crm_negotiation_funnel_id', $funnel->id)
                        ->where('order', $stepTemplate['order'])
                        ->first();

                    if ($existingByOrder) {
                        // Update existing step if name differs
                        if ($existingByOrder->name !== $stepTemplate['name']) {
                            $existingByOrder->fill([
                                'name' => $stepTemplate['name'],
                                'color' => $stepTemplate['color'],
                                'is_active' => $stepTemplate['is_active'],
                            ]);
                            $existingByOrder->save();
                        }

                        continue;
                    }

                    // Check by name
                    $step = CRMNegotiationFunnelStep::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('crm_negotiation_funnel_id', $funnel->id)
                        ->where('name', $stepTemplate['name'])
                        ->first();

                    if ($step) {
                        $step->fill([
                            'color' => $stepTemplate['color'],
                            'is_active' => $stepTemplate['is_active'],
                            'order' => $stepTemplate['order'],
                        ]);
                        $step->save();
                    } else {
                        \Domain\CRM\Models\CRMNegotiationFunnelStep::query()->create([
                            'id' => (string) Str::orderedUuid(),
                            'tenant_id' => $tenant->id,
                            'crm_negotiation_funnel_id' => $funnel->id,
                            'name' => $stepTemplate['name'],
                            'color' => $stepTemplate['color'],
                            'is_active' => $stepTemplate['is_active'],
                            'order' => $stepTemplate['order'],
                        ]);
                    }
                }
            }
        }

        $this->command->info('✅ Funis de negociação e etapas sincronizados para todos os tenants.');
    }
}
