<?php

declare(strict_types=1);

namespace Domain\Billing\Console\Commands;

use Carbon\CarbonImmutable;
use Domain\Billing\Actions\BillingInvoiceActions;
use Domain\Billing\Actions\CalculateAiOverageAction;
use Domain\Billing\DTOs\BillingInvoiceDTO;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Scopes\TenantScope;
use Illuminate\Console\Command;

/**
 * Gera faturas mensais de assinatura com excedente de IA.
 */
final class BillingGenerateMonthlyInvoicesCommand extends Command
{
    protected $signature = 'billing:generate-monthly-invoices
                            {reference_month? : Mês de referência no formato YYYY-MM}
                            {--dry-run : Lista as faturas sem persistir}';

    protected $description = 'Gera faturas mensais para tenants ativos com plano contratado.';

    public function __construct(
        private readonly CalculateAiOverageAction $calculateAiOverage,
        private readonly BillingInvoiceActions $billingInvoiceActions,
    ) {
        parent::__construct();
    }

    /** Executa a geração de faturas mensais para todos os tenants ativos com plano. */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $referenceMonth = $this->resolveReferenceMonth();
        $dueDate = CarbonImmutable::createFromFormat('Y-m-d', $referenceMonth.'-01')
            ->addMonth()
            ->day(10)
            ->toDateString();

        $created = 0;
        $skipped = 0;
        $rows = [];

        PlatformTenant::query()
            ->with('plan')
            ->where('is_active', true)
            ->where('billing_status', BillingTenantStatus::ACTIVE->value)
            ->whereNotNull('plan_id')
            ->orderBy('name')
            ->each(function (PlatformTenant $tenant) use ($dryRun, $dueDate, $referenceMonth, &$created, &$skipped, &$rows): void {
                $plan = $tenant->plan;

                if ($plan === null) {
                    $skipped++;

                    return;
                }

                $exists = BillingInvoice::query()
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenant->id)
                    ->where('reference_month', $referenceMonth)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    $rows[] = [$tenant->name, $referenceMonth, 'já existe', (string) $plan->price_monthly, '0.00', '0.00'];

                    return;
                }

                $overage = $this->calculateAiOverage->execute((string) $tenant->id, $referenceMonth);
                $basePrice = (float) $plan->price_monthly;
                $overageAmount = (float) $overage['overage_amount'];
                $amount = round($basePrice + $overageAmount, 2);

                $rows[] = [
                    $tenant->name,
                    $referenceMonth,
                    $dryRun ? 'dry-run' : 'criada',
                    number_format($basePrice, 2, '.', ''),
                    number_format($overageAmount, 2, '.', ''),
                    number_format($amount, 2, '.', ''),
                ];

                if ($dryRun) {
                    return;
                }

                $this->billingInvoiceActions->create((string) $tenant->id, BillingInvoiceDTO::fromArray([
                    'plan_id' => $plan->id,
                    'reference_month' => $referenceMonth,
                    'amount' => $amount,
                    'status' => BillingInvoiceStatus::DRAFT->value,
                    'due_date' => $dueDate,
                    'metadata' => [
                        'base_price' => $basePrice,
                        'total_tokens' => $overage['total_tokens'],
                        'token_limit_monthly' => $overage['token_limit_monthly'],
                        'overage_applied' => $overage['overage_applied'],
                        'overage_tokens' => $overage['overage_tokens'],
                        'overage_amount' => $overageAmount,
                    ],
                ]));

                $created++;
            });

        $this->info('Billing monthly invoices finalizado.');
        $this->table(
            ['tenant', 'reference_month', 'status', 'base_price', 'overage', 'amount'],
            $rows
        );
        $this->line(sprintf('Criadas: %d | Ignoradas: %d | Dry-run: %s', $created, $skipped, $dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }

    /** Retorna o mês de referência do argumento ou, se ausente, o mês anterior ao atual. */
    private function resolveReferenceMonth(): string
    {
        $argument = $this->argument('reference_month');

        if (is_string($argument) && preg_match('/^\d{4}-\d{2}$/', $argument) === 1) {
            return $argument;
        }

        return CarbonImmutable::now()->subMonthNoOverflow()->format('Y-m');
    }
}
