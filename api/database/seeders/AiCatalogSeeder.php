<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Enums\AiToolEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persists bootstrap catalogs used by PlatformTenantBootstrapCatalogService.
 */
class AiCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $segmentCode => $payload) {
            DB::table('platform_tenant_bootstrap_catalogs')->updateOrInsert(
                ['segment_code' => $segmentCode],
                [
                    'id' => (string) Str::orderedUuid(),
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalog(): array
    {
        $general = [
            'prompt_suffix' => 'Atue com foco em atendimento consultivo, segurança e geração de oportunidades qualificadas.',
            'agents' => [],
            'funnels' => [
                [
                    'name' => 'Vendas Padrão',
                    'steps' => [
                        ['name' => 'Novo Lead', 'order' => 1, 'color' => '#3B82F6'],
                        ['name' => 'Qualificação', 'order' => 2, 'color' => '#8B5CF6'],
                        ['name' => 'Proposta', 'order' => 3, 'color' => '#F59E0B'],
                        ['name' => 'Fechado - Ganho', 'order' => 4, 'color' => '#10B981'],
                        ['name' => 'Perdido', 'order' => 5, 'color' => '#6B7280'],
                    ],
                ],
            ],
            'loss_reasons' => [
                'Preço acima do esperado',
                'Sem retorno do cliente',
                'Concorrência mais forte',
                'Sem fit com necessidade',
            ],
            'tags' => ['Quente', 'Morno', 'Frio', 'VIP', 'Acompanhamento'],
            'departments' => ['Comercial', 'Suporte', 'Customer Success', 'Financeiro'],
        ];

        $saas = $general;
        $saas['prompt_suffix'] = 'Atue como especialista em força de vendas SaaS, focando MQL, SQL, trial, ativação e expansão.';
        $saas['tags'] = ['Lead Qualificado', 'Lead Vendas', 'Período Teste', 'Integração', 'Expansão'];
        $saas['departments'] = ['Pré-vendas', 'Vendas', 'Onboarding', 'Customer Success'];
        $saas['agents'] = [
            [
                'name' => 'Super Admin - SAAS',
                'type' => 'general',
                'model_id' => 'gpt-4o-mini',
                'system_prompt' => 'Atue como operador principal da operação SaaS com foco em triagem e encaminhamento eficiente.',
                'classifier_model' => 'gpt-4o-mini',
                'max_tokens' => 2048,
                'temperature' => 0.5,
                'top_p' => 1.0,
                'token_budget_input' => 5000,
                'token_budget_output' => 2000,
                'fallback_message' => 'Vou acionar um especialista humano para continuidade.',
                'skills' => [
                    [
                        'name' => 'Autopilot Orchestration',
                        'description' => 'Orquestra agentes especializados e decide escalonamento conforme contexto.',
                        'metadata' => [
                            'prompt_append' => '=== ORQUESTRAÇÃO === Orquestre triagem, qualificação e escalonamento humano quando necessário.',
                        ],
                    ],
                ],
                'files' => [
                    [
                        'slug' => 'IDENTITY.md',
                        'content' => '# Quem sou eu\n\nSou o Super Admin da operação SaaS.',
                    ],
                ],
                'channels' => ['whatsapp'],
                'tools' => [
                    AiToolEnum::SEARCH_KNOWLEDGE,
                    AiToolEnum::SEND_MESSAGE,
                    AiToolEnum::TRANSFER_TO_HUMAN,
                ],
            ],
        ];

        $ecommerce = $general;
        $ecommerce['prompt_suffix'] = 'Atue como especialista em e-commerce, focando em conversão, carrinho abandonado, rastreio de pedidos e pós-venda com excelência.';
        $ecommerce['tags'] = ['Carrinho Abandonado', 'Primeira Compra', 'Recorrente', 'VIP', 'Troca e Devolução'];
        $ecommerce['departments'] = ['Vendas Online', 'SAC', 'Logística', 'Pós-venda'];

        $healthcare = $general;
        $healthcare['prompt_suffix'] = 'Atue com acolhimento, sigilo absoluto e foco em agendamento e orientações pré/pós-consulta. Nunca diagnostique ou prescreva.';
        $healthcare['tags'] = ['Urgente', 'Retorno', 'Primeira Consulta', 'Convênio', 'Particular'];
        $healthcare['departments'] = ['Recepção', 'Agendamento', 'Atendimento', 'Financeiro'];

        $realEstate = $general;
        $realEstate['prompt_suffix'] = 'Atue como consultor imobiliário digital especializado em imóveis residenciais e comerciais, agendamento de visitas e acompanhamento documental.';
        $realEstate['tags'] = ['Compra', 'Aluguel', 'Financiamento', 'Visita Agendada', 'Documentação'];
        $realEstate['departments'] = ['Vendas', 'Locação', 'Documental', 'Financeiro'];

        return [
            'GENERAL' => $general,
            'SAAS' => $saas,
            'ECOMMERCE' => $ecommerce,
            'HEALTHCARE' => $healthcare,
            'REAL_ESTATE' => $realEstate,
        ];
    }
}
