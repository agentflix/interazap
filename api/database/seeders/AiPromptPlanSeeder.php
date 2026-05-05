<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Models\AiPromptPlan;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Database\Seeder;

/**
 * Seed plan-level AI prompt rules for all existing platform plans.
 */
class AiPromptPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = PlatformPlan::query()->get();

        if ($plans->isEmpty()) {
            $this->command->warn('No platform plans found. Skipping AiPromptPlanSeeder.');

            return;
        }

        $count = 0;

        foreach ($plans as $plan) {
            $content = match ($plan->slug) {
                'starter', 'basic' => 'REGRAS DO PLANO STARTER/BASIC: mantenha respostas muito curtas com no máximo 80 palavras, linguagem simples e direta, sem tabelas, sem formatação rica e sem exemplos extensos. Priorize uma orientação objetiva e um próximo passo único por resposta.',
                'medium' => 'REGRAS DO PLANO MÉDIO — COMPORTAMENTO DE RESPOSTA: máximo 150 palavras por resposta. Estruture em problema, solução e próximo passo. Use listas curtas com até 5 itens, evite contexto excessivo e priorize objetividade. Se necessário, ofereça resumo e pergunte se o cliente deseja aprofundar.',
                'pro', 'professional' => 'REGRAS DO PLANO PRO/PROFESSIONAL: respostas completas com até 300 palavras, com explicações claras, exemplos pontuais e formatação moderada. Pode usar listas quando ajudar na clareza, mas evite prolixidade. Inclua recomendação prática de execução ao final.',
                'master', 'enterprise' => 'REGRAS DO PLANO MASTER/ENTERPRISE: forneça respostas elaboradas e de alto valor, sem limite rígido de palavras quando necessário. Inclua contexto, alternativas com prós e contras, exemplos, tabelas comparativas quando útil e abordagem proativa. Antecipe dúvidas correlatas e proponha próximos passos concretos.',
                default => sprintf('Prompt rules for plan %s.', $plan->name),
            };

            AiPromptPlan::query()->updateOrCreate(
                ['plan_id' => $plan->id],
                [
                    'content' => $content,
                    'is_active' => true,
                ]
            );

            $count++;
        }

    }
}
