<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Models\AiAutopilotGuardrail;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;

/**
 * Seed tenant-scoped autopilot guardrails.
 */
class AiAutopilotGuardrailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping AiAutopilotGuardrailSeeder.');

            return;
        }

        $guardrails = [
            ['name' => 'Bloquear Exposição de PII', 'rule_type' => 'BLOCK', 'description' => 'Detecta CPF/CNPJ/email em resposta', 'conditions' => ['pattern' => '(cpf|cnpj|@)']],
            ['name' => 'Alertar Sobrescrita de Preço', 'rule_type' => 'WARN', 'description' => 'Desconto > 30%', 'conditions' => ['discount_percent_gt' => 30]],
            ['name' => 'Registrar Todas as Chamadas de Ferramenta', 'rule_type' => 'LOG_ONLY', 'description' => 'Qualquer execução de ferramenta', 'conditions' => ['tool_call' => true]],
            ['name' => 'Bloquear Operações de Exclusão', 'rule_type' => 'BLOCK', 'description' => 'Tentativa de deletar dados do cliente', 'conditions' => ['operation' => 'delete']],
            ['name' => 'Alertar Resposta Longa', 'rule_type' => 'WARN', 'description' => 'Resposta > 500 tokens', 'conditions' => ['response_tokens_gt' => 500]],
            ['name' => 'Bloquear Menção de Concorrente', 'rule_type' => 'BLOCK', 'description' => 'Menção a concorrentes listados', 'conditions' => ['competitor_mentions' => ['concorrente-a', 'concorrente-b']]],
        ];

        $created = 0;

        foreach ($tenants as $tenant) {
            foreach ($guardrails as $index => $guardrail) {
                AiAutopilotGuardrail::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $guardrail['name'],
                    ],
                    [
                        'description' => $guardrail['description'],
                        'rule_type' => $guardrail['rule_type'],
                        'conditions' => $guardrail['conditions'],
                        'priority' => $index + 1,
                        'is_active' => true,
                    ]
                );

                $created++;
            }
        }

        $this->command->info(sprintf('AI Autopilot Guardrails seeded: %d', $created));
    }
}
