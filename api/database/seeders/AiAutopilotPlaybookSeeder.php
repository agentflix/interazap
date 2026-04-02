<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Models\AiAutopilotPlaybook;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;

/**
 * Seed tenant-scoped autopilot playbooks.
 */
class AiAutopilotPlaybookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping AiAutopilotPlaybookSeeder.');

            return;
        }

        $playbooks = [
            [
                'name' => 'Atendimento Inicial',
                'description' => 'Saudação → Identificação → Classificação → Roteamento',
                'steps' => ['Saudação', 'Identificação', 'Classificação', 'Roteamento'],
            ],
            [
                'name' => 'Qualificação de Lead',
                'description' => 'Coleta dados → Score → Notifica vendedor → Agenda reunião',
                'steps' => ['Coleta dados', 'Score', 'Notifica vendedor', 'Agenda reunião'],
            ],
            [
                'name' => 'Pós-Venda D+1',
                'description' => 'Verifica satisfação → Coleta feedback → Registra nota',
                'steps' => ['Verifica satisfação', 'Coleta feedback', 'Registra nota'],
            ],
            [
                'name' => 'Recuperação de Carrinho',
                'description' => 'Identifica abandono → Envia mensagem → Oferece desconto',
                'steps' => ['Identifica abandono', 'Envia mensagem', 'Oferece desconto'],
            ],
            [
                'name' => 'Suporte Técnico L1',
                'description' => 'Diagnóstico → Busca KB → Responde → Escala se necessário',
                'steps' => ['Diagnóstico', 'Busca KB', 'Responde', 'Escala se necessário'],
            ],
        ];

        $created = 0;

        foreach ($tenants as $tenant) {
            foreach ($playbooks as $playbook) {
                AiAutopilotPlaybook::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $playbook['name'],
                    ],
                    [
                        'description' => $playbook['description'],
                        'version' => 1,
                        'steps' => array_map(
                            static fn (string $step, int $index): array => [
                                'order' => $index + 1,
                                'name' => $step,
                            ],
                            $playbook['steps'],
                            array_keys($playbook['steps'])
                        ),
                        'metadata' => ['seed_source' => 'ai_module_seeder'],
                        'is_active' => true,
                    ]
                );

                $created++;
            }
        }

        $this->command->info(sprintf('AI Autopilot Playbooks seeded: %d', $created));
    }
}
