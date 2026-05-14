<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMNegotiationProduct;
use Domain\CRM\Models\CRMProduct;
use Domain\CRM\Models\CRMProposal;
use Domain\CRM\Models\CRMReasonLoss;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds CRM data for report testing.
 *
 * Covers: Sales Funnel, Revenue Sales, Salesperson Performance,
 * Loss Reasons, Product Performance reports.
 */
final class ReportsCrmSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('Nenhum tenant encontrado. Execute DatabaseSeeder primeiro.');

            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedCrmData($tenant->id);
        }
    }

    private function seedCrmData(string $tenantId): void
    {
        // Clear existing data to avoid unique constraint violations
        \Domain\CRM\Models\CRMNegotiationProduct::query()->where('tenant_id', $tenantId)->delete();
        \Domain\CRM\Models\CRMProposal::query()->where('tenant_id', $tenantId)->delete();
        \Domain\CRM\Models\CRMNegotiation::query()->where('tenant_id', $tenantId)->delete();
        \Domain\CRM\Models\CRMProduct::query()->where('tenant_id', $tenantId)->delete();
        \Domain\CRM\Models\CRMContact::query()->where('tenant_id', $tenantId)->delete();
        \Domain\CRM\Models\CRMNegotiationFunnel::query()->where('tenant_id', $tenantId)->delete();
        // Note: CRMReasonLoss is intentionally not deleted to allow re-runs

        // Get agents for this tenant
        $agents = AuthUser::query()
            ->where('tenant_id', $tenantId)
            ->get();

        if ($agents->isEmpty()) {
            $this->command->warn("Nenhum agente encontrado para tenant {$tenantId}");

            return;
        }

        $agentIds = $agents->pluck('id')->toArray();

        // Get funnel steps
        $steps = CRMNegotiationFunnelStep::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('order')
            ->get();

        if ($steps->isEmpty()) {
            $this->command->warn("Nenhuma etapa de funil encontrada para tenant {$tenantId}");

            return;
        }

        // Create loss reasons if not exist
        $lossReasons = $this->seedLossReasons($tenantId);

        // Create negotiations with various statuses
        $this->seedNegotiations($tenantId, $agentIds, $steps, $lossReasons);

        // Create proposals for some negotiations
        $this->seedProposals($tenantId);

        // Create products and link to negotiations
        $this->seedProductsAndLinks($tenantId);
    }

    /**
     * @return array<int, \Domain\CRM\Models\CRMReasonLoss>
     */
    private function seedLossReasons(string $tenantId): array
    {
        $reasons = [
            'Preço alto',
            'Concorrência',
            'Falta de interesse',
            'Produto não atende',
            'Prazo de entrega',
        ];

        $created = [];
        foreach ($reasons as $reason) {
            $existing = \Domain\CRM\Models\CRMReasonLoss::query()->where('tenant_id', $tenantId)
                ->where('name', $reason)
                ->first();
            if ($existing) {
                $created[] = $existing;
            } else {
                $created[] = CRMReasonLoss::factory()->create([
                    'tenant_id' => $tenantId,
                    'name' => $reason,
                    'description' => $reason,
                    'is_active' => true,
                ]);
            }
        }

        return $created;
    }

    /**
     * @param  array<int, string>  $agentIds
     * @param  \Illuminate\Support\Collection<int, \Domain\CRM\Models\CRMNegotiationFunnelStep>  $steps
     * @param  array<int, \Domain\CRM\Models\CRMReasonLoss>  $lossReasons
     */
    private function seedNegotiations(
        string $tenantId,
        array $agentIds,
        $steps,
        array $lossReasons
    ): void {
        $statuses = ['open', 'won', 'lost'];

        // Create 30 negotiations
        for ($i = 0; $i < 30; $i++) {
            $status = $statuses[array_rand($statuses)];
            $step = $steps->random();

            $negotiation = CRMNegotiation::factory()
                ->create([
                    'tenant_id' => $tenantId,
                    'auth_user_id' => $agentIds[array_rand($agentIds)],
                    'status' => $status,
                    'crm_negotiation_funnel_step_id' => $step->id,
                    'closed_at' => $status !== 'open' ? now()->subDays(random_int(1, 30)) : null,
                    'amount' => random_int(1000, 10000),
                ]);

            // Link to loss reason if lost
            if ($status === 'lost' && $lossReasons !== []) {
                $lossReason = $lossReasons[array_rand($lossReasons)];
                $negotiation->update(['crm_reason_loss_id' => $lossReason->id]);
            }
        }
    }

    private function seedProposals(string $tenantId): void
    {
        $negotiations = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'open')
            ->get();

        $statuses = ['draft', 'sent', 'accepted', 'rejected'];

        foreach ($negotiations as $negotiation) {
            CRMProposal::factory()
                ->count(random_int(1, 3))
                ->create([
                    'tenant_id' => $tenantId,
                    'crm_negotiation_id' => $negotiation->id,
                    'status' => fn (): string => $statuses[array_rand($statuses)],
                    'total' => $negotiation->amount ?? random_int(1000, 10000),
                ]);
        }
    }

    private function seedProductsAndLinks(string $tenantId): void
    {
        // Create products
        $products = [];
        for ($i = 0; $i < 10; $i++) {
            $products[] = CRMProduct::factory()
                ->create([
                    'tenant_id' => $tenantId,
                ]);
        }

        // Link products to negotiations
        $negotiations = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->get();

        foreach ($negotiations as $negotiation) {
            $randomProducts = collect($products)->random(random_int(1, 3));
            foreach ($randomProducts as $product) {
                CRMNegotiationProduct::factory()
                    ->create([
                        'tenant_id' => $tenantId,
                        'crm_negotiation_id' => $negotiation->id,
                        'crm_product_id' => $product->id,
                        'quantity' => random_int(1, 5),
                        'unit_price' => $product->price ?? random_int(100, 1000),
                    ]);
            }
        }
    }
}
