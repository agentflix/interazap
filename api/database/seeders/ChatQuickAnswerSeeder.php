<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Chat\Models\ChatQuickAnswer;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChatQuickAnswerSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenant = PlatformTenant::query()->first();

        if (! $tenant) {
            $this->command->warn('Nenhum tenant encontrado. Execute DatabaseSeeder primeiro.');

            return;
        }

        $quickAnswers = [
            [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Saudação Inicial',
                'shortcut' => '/ola',
                'content' => 'Olá! Seja bem-vindo(a) ao nosso atendimento. Como posso ajudá-lo(a) hoje?',
                'category' => 'Saudações',
                'is_active' => true,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Horário de Atendimento',
                'shortcut' => '/horario',
                'content' => 'Nosso horário de atendimento é de segunda a sexta-feira, das 8h às 18h. Sábados das 9h às 13h.',
                'category' => 'Informações',
                'is_active' => true,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Prazo de Entrega',
                'shortcut' => '/prazo',
                'content' => 'O prazo de entrega varia de acordo com a sua localização e o produto escolhido. Em média, são de 7 a 15 dias úteis.',
                'category' => 'Vendas',
                'is_active' => true,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Formas de Pagamento',
                'shortcut' => '/pagamento',
                'content' => 'Aceitamos cartão de crédito (até 12x), débito, PIX e boleto bancário. Para empresas, também oferecemos pagamento via fatura.',
                'category' => 'Vendas',
                'is_active' => true,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Política de Devolução',
                'shortcut' => '/devolucao',
                'content' => 'Você tem até 7 dias corridos para solicitar a devolução do produto, conforme o Código de Defesa do Consumidor. O produto deve estar em perfeito estado e na embalagem original.',
                'category' => 'Pós-Venda',
                'is_active' => true,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Suporte Técnico',
                'shortcut' => '/suporte',
                'content' => 'Para suporte técnico, entre em contato com nossa equipe especializada através do email suporte@agentflix.local ou pelo telefone (11) 3000-0000.',
                'category' => 'Suporte',
                'is_active' => true,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Rastreamento de Pedido',
                'shortcut' => '/rastreio',
                'content' => 'Para rastrear seu pedido, acesse nosso portal com seu CPF/CNPJ e número do pedido. Você também receberá atualizações por email e SMS.',
                'category' => 'Pós-Venda',
                'is_active' => true,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Despedida Padrão',
                'shortcut' => '/tchau',
                'content' => 'Obrigado pelo contato! Se precisar de mais alguma coisa, estamos à disposição. Tenha um ótimo dia! 😊',
                'category' => 'Despedidas',
                'is_active' => true,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Aguardar Informações',
                'shortcut' => '/aguarde',
                'content' => 'Por favor, aguarde alguns instantes enquanto verifico essas informações para você.',
                'category' => 'Atendimento',
                'is_active' => true,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Solicitar Dados',
                'shortcut' => '/dados',
                'content' => 'Para prosseguir com seu atendimento, preciso de algumas informações: Nome completo, CPF/CNPJ e número do pedido (se houver).',
                'category' => 'Atendimento',
                'is_active' => true,
            ],
        ];

        foreach ($quickAnswers as $answer) {
            ChatQuickAnswer::query()->updateOrCreate(
                ['shortcut' => $answer['shortcut'], 'tenant_id' => $tenant->id],
                $answer
            );
        }

        $this->command->info('✅ Templates de mensagens (Quick Answers) criados com sucesso!');
    }
}
