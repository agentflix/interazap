<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\CRM\Models\CRMProduct;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CRMProductSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, array{name: string, description: string, type: string, price: float, track_stock: bool, stock: int}>
     */
    private const PRODUCT_CATALOG = [
        [
            'name' => 'Plano CRM Essencial',
            'description' => 'Assinatura mensal para até 5 usuários com funil e automações básicas.',
            'type' => 'service',
            'price' => 297.00,
            'track_stock' => false,
            'stock' => 0,
        ],
        [
            'name' => 'Plano CRM Professional',
            'description' => 'Assinatura mensal com funil avançado, propostas e relatórios completos.',
            'type' => 'service',
            'price' => 697.00,
            'track_stock' => false,
            'stock' => 0,
        ],
        [
            'name' => 'Implantação CRM Premium',
            'description' => 'Projeto de implantação com parametrização de funis e treinamento inicial.',
            'type' => 'service',
            'price' => 4800.00,
            'track_stock' => false,
            'stock' => 0,
        ],
        [
            'name' => 'Consultoria Comercial 8h',
            'description' => 'Pacote mensal de consultoria para otimização do processo comercial.',
            'type' => 'service',
            'price' => 2200.00,
            'track_stock' => false,
            'stock' => 0,
        ],
        [
            'name' => 'Integração WhatsApp API',
            'description' => 'Ativação e configuração da integração oficial de WhatsApp para atendimento.',
            'type' => 'service',
            'price' => 1800.00,
            'track_stock' => false,
            'stock' => 0,
        ],
        [
            'name' => 'Pacote de Treinamento In Company',
            'description' => 'Treinamento presencial para equipe comercial e operação.',
            'type' => 'service',
            'price' => 3200.00,
            'track_stock' => false,
            'stock' => 0,
        ],
        [
            'name' => 'Notebook Comercial Pro 14"',
            'description' => 'Equipamento para equipe de vendas externas com alta autonomia.',
            'type' => 'product',
            'price' => 4599.00,
            'track_stock' => true,
            'stock' => 45,
        ],
        [
            'name' => 'Headset Corporativo USB',
            'description' => 'Headset com cancelamento de ruído para operação de pré-vendas.',
            'type' => 'product',
            'price' => 389.00,
            'track_stock' => true,
            'stock' => 180,
        ],
        [
            'name' => 'Smartphone Comercial 5G',
            'description' => 'Aparelho para campo com suporte a apps de CRM e comunicação.',
            'type' => 'product',
            'price' => 2399.00,
            'track_stock' => true,
            'stock' => 72,
        ],
        [
            'name' => 'Licença BI de Vendas',
            'description' => 'Módulo analítico com dashboards de conversão e forecast.',
            'type' => 'service',
            'price' => 1190.00,
            'track_stock' => false,
            'stock' => 0,
        ],
        [
            'name' => 'Integração ERP Financeiro',
            'description' => 'Conector para sincronizar clientes, propostas e faturamento.',
            'type' => 'service',
            'price' => 3600.00,
            'track_stock' => false,
            'stock' => 0,
        ],
        [
            'name' => 'Pacote SLA Suporte 24x7',
            'description' => 'Cobertura estendida para suporte com atendimento prioritário.',
            'type' => 'service',
            'price' => 1490.00,
            'track_stock' => false,
            'stock' => 0,
        ],
    ];

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        foreach ($tenants as $tenant) {
            foreach (self::PRODUCT_CATALOG as $item) {
                $product = CRMProduct::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $item['name'],
                    ],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'description' => $item['description'],
                        'type' => $item['type'],
                        'price' => $item['price'],
                        'is_active' => true,
                        'track_stock' => $item['track_stock'],
                        'stock' => $item['stock'],
                    ]
                );

                $product->fill([
                    'description' => $item['description'],
                    'type' => $item['type'],
                    'price' => $item['price'],
                    'is_active' => true,
                    'track_stock' => $item['track_stock'],
                    'stock' => $item['stock'],
                ]);
                $product->save();
            }
        }

        $this->command->info(sprintf('CRM products ensured per tenant: %d', count(self::PRODUCT_CATALOG)));
    }
}
