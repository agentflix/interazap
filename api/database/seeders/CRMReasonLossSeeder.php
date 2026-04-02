<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\CRM\Models\CRMReasonLoss;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed realistic CRM loss reasons for every tenant.
 */
class CRMReasonLossSeeder extends Seeder
{
    use WithoutModelEvents;

    private const REASONS = [
        'Preço acima do esperado',
        'Concorrência mais forte',
        'Não havia fit com a necessidade',
        'Orçamento congelado',
        'Decisão adiada',
        'Sem retorno do cliente',
        'Produto indisponível',
        'Mudança de prioridade',
        'Não aprovado pela diretoria',
        'Problema de prazo',
        'Escopo solicitado fora do contrato',
        'Integração técnica inviável no momento',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('Nenhum tenant encontrado. Execute DatabaseSeeder primeiro.');

            return;
        }

        foreach ($tenants as $tenant) {
            foreach (self::REASONS as $reason) {
                CRMReasonLoss::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $reason],
                    ['id' => (string) Str::orderedUuid()]
                );
            }
        }

        $this->command->info(sprintf('Reason losses ensured per tenant: %d', count(self::REASONS)));
    }
}
