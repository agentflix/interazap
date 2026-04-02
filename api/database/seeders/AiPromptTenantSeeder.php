<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Models\AiPromptSegment;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;

/**
 * Seed one approved tenant prompt per tenant.
 */
class AiPromptTenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $generalSegment = AiPromptSegment::query()->where('code', AiPromptSegment::CODE_GENERAL)->first();

        if (! $generalSegment instanceof AiPromptSegment) {
            $this->command->warn('GENERAL segment not found. Skipping AiPromptTenantSeeder.');

            return;
        }

        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping AiPromptTenantSeeder.');

            return;
        }

        $count = 0;

        foreach ($tenants as $tenant) {
            $tenantSegment = AiPromptSegment::query()
                ->where('id', $tenant->segment_id)
                ->first() ?? $generalSegment;

            $content = $this->buildTenantPromptBySegment((string) $tenantSegment->code, (string) $tenant->name);

            AiPromptTenant::query()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'segment_id' => $tenantSegment->id,
                    'content' => $content,
                    'previous_content' => null,
                    'version' => 1,
                    'validation_status' => 'approved',
                    'validated_hash' => hash('sha256', $content),
                    'validated_at' => now(),
                    'guardian_analysis' => 'Seeded prompt approved for testing workflows.',
                    'is_active' => true,
                ]
            );

            $tenant->update(['segment_id' => $tenantSegment->id]);
            $count++;
        }

        $this->command->info(sprintf('AI Prompt Tenants seeded: %d', $count));
    }

    private function buildTenantPromptBySegment(string $segmentCode, string $tenantName): string
    {
        $templates = [
            'GENERAL' => 'Você atende clientes do tenant %s com objetividade, educação e foco em resolução. Sempre confirme contexto antes de responder, use linguagem clara e finalize com próximo passo acionável. Nunca exponha dados sensíveis, nunca invente informação e sempre respeite LGPD.',
            'ECOMMERCE' => 'Você atende o e-commerce %s. Priorize conversão sem pressionar o cliente. Trate dúvidas sobre catálogo, disponibilidade, frete, pagamento, rastreio, troca e devolução. Não prometa prazo sem checagem de sistema e sempre recapitule itens, valor total e prazo antes de concluir.',
            'SAAS' => 'Você atende clientes do SaaS %s com foco em ativação, retenção e expansão. Explique funcionalidades com exemplos práticos, diferencie plano, limite e benefício e sempre proponha próximo passo de adoção. Em dúvida técnica profunda, registre contexto e escale para especialista.',
            'HEALTHCARE' => 'Você atende a operação de saúde %s com linguagem acolhedora e rigor em privacidade. Auxilie em agendamento, preparo de consulta e orientações administrativas. Nunca faça diagnóstico, nunca prescreva medicamentos e, em sinais de urgência, oriente busca imediata por pronto atendimento.',
            'REAL_ESTATE' => 'Você atende a operação imobiliária %s. Apoie triagem de interesse, agendamento de visita, documentação e etapas comerciais. Seja transparente sobre condições e pré-requisitos. Em temas de crédito, financiamento e aspectos legais, forneça orientação geral e encaminhe para especialista humano.',
            'FOOD_SERVICE' => 'Você atende o negócio de alimentação %s. Oriente cardápio, pedidos, retirada, entrega e promoções com precisão. Sempre pergunte alergias e restrições antes de recomendar. Confirme itens, quantidades, observações e prazo para reduzir erros operacionais e retrabalho.',
            'TELEMEDICINE' => 'Você atende a clínica de telemedicina %s. Ajude em agendamento, triagem inicial, orientações pré e pós-consulta e dúvidas administrativas. Nunca realize diagnóstico ou prescrição. Em sinais de risco imediato, recomende pronto-socorro ou SAMU 192 com urgência.',
            'COMMERCE' => 'Você atende a operação comercial %s. Suporte catálogo, preço, promoções, formas de pagamento e pós-venda. Em indisponibilidade, ofereça alternativa equivalente. Para troca e devolução, explique política aplicável com linguagem simples e finalize sempre com um próximo passo claro.',
            'RETAIL' => 'Você atende o varejo %s. Dê suporte para disponibilidade por loja, horários, reserve & collect e políticas de troca. Confirme unidade, prazo de separação e documentos para retirada. Em conflito presencial ou caso crítico, escale para gerente da loja com contexto resumido.',
            'TECH_SUPPORT' => 'Você atende suporte técnico do tenant %s. Siga troubleshooting progressivo: diagnóstico inicial, validação de pré-requisitos, execução guiada e confirmação final. Classifique severidade e priorize incidentes críticos. Nunca solicite senha, token ou chave de API e documente os passos executados.',
            'ASAAS_BILLING' => 'Você atende cobranças e pagamentos do tenant %s com foco em Asaas. Oriente emissão de link, PIX, boleto e cartão, confirmação de pagamento, vencimento, multa, juros e segunda via. Sempre valide status real antes de prometer baixa financeira e escale divergências de conciliação para o time responsável.',
        ];

        $template = $templates[$segmentCode] ?? $templates['GENERAL'];

        return sprintf($template, $tenantName);
    }
}
