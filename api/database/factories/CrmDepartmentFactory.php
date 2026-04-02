<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMDepartment>
 */
class CRMDepartmentFactory extends Factory
{
    protected $model = CRMDepartment::class;

    private const array DEPARTMENT_TYPES = [
        'Vendas' => [
            'description' => 'Departamento responsável pela gestão da equipe de vendas, prospecção de clientes e fechamento de negócios.',
        ],
        'Atendimento' => [
            'description' => 'Departamento focado no suporte e atendimento ao cliente, resolução de dúvidas e satisfação do usuário.',
        ],
        'Suporte' => [
            'description' => 'Departamento dedicado ao suporte técnico, resolução de problemas e assistência aos clientes.',
        ],
        'Financeiro' => [
            'description' => 'Departamento responsável pela gestão financeira, controle de contas a pagar e a receber.',
        ],
        'Marketing' => [
            'description' => 'Departamento responsável pelas estratégias de marketing, campanhas e comunicação institucional.',
        ],
        'RH' => [
            'description' => 'Departamento de Recursos Humanos focado em recrutamento, seleção e gestão de pessoas.',
        ],
        'Tecnologia' => [
            'description' => 'Departamento de tecnologia e desenvolvimento de sistemas, inovação e infraestrutura técnica.',
        ],
        'Operações' => [
            'description' => 'Departamento responsável pelas operações logísticas, processos internos e eficiência operacional.',
        ],
        'Comercial' => [
            'description' => 'Departamento comercial focado em estratégias de negócio, parcerias e expansão de mercado.',
        ],
        'Produção' => [
            'description' => 'Departamento responsável pela produção, manufatura e controle de qualidade dos produtos.',
        ],
        'Jurídico' => [
            'description' => 'Departamento jurídico responsável por questões legais, contratos e conformidade regulatória.',
        ],
        'Logística' => [
            'description' => 'Departamento de logística responsável por transporte, armazenagem e distribuição.',
        ],
        'Contabilidade' => [
            'description' => 'Departamento de contabilidade focado em registros financeiros, relatórios e compliance.',
        ],
        'Compras' => [
            'description' => 'Departamento responsável pela aquisição de produtos, serviços e gestão de fornecedores.',
        ],
        'Qualidade' => [
            'description' => 'Departamento focado em controle de qualidade, padrões e melhoria contínua de processos.',
        ],
        'Desenvolvimento' => [
            'description' => 'Departamento de desenvolvimento de produtos, pesquisa e inovação técnica.',
        ],
        'Planejamento' => [
            'description' => 'Departamento de planejamento estratégico, metas e acompanhamento de resultados.',
        ],
        'Comunicação' => [
            'description' => 'Departamento de comunicação interna e externa, relações públicas e branding.',
        ],
        'Projetos' => [
            'description' => 'Departamento responsável pela gestão de projetos, cronogramas e entregas.',
        ],
        'Customer Success' => [
            'description' => 'Departamento focado no sucesso do cliente, onboarding e retenção.',
        ],
    ];

    public function definition(): array
    {
        $type = collect(self::DEPARTMENT_TYPES)->random();

        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->unique()->company(),
            'description' => $type['description'],
            'is_active' => true,
        ];
    }
}
