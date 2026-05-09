<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds billing data for report testing.
 *
 * Covers: Billing Report
 */
final class ReportsBillingSeeder extends Seeder
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
            $this->seedInvoices($tenant->id);
        }
    }

    private function seedInvoices(string $tenantId): void
    {
        // Clear existing invoices for this tenant to avoid unique constraint violations
        \Domain\Billing\Models\BillingInvoice::query()->where('tenant_id', $tenantId)->delete();

        $statuses = ['paid', 'pending', 'overdue', 'draft'];
        $paymentMethods = ['credit_card', 'pix', 'bank_transfer', 'boleto'];

        // Create 12 invoices (1 per month for the last 12 months)
        for ($i = 0; $i < 12; $i++) {
            $status = $statuses[array_rand($statuses)];
            $referenceMonth = now()->subMonths($i)->format('Y-m');
            $dueDate = now()->subMonths($i)->addDays(random_int(5, 30));

            BillingInvoice::factory()
                ->create([
                    'tenant_id' => $tenantId,
                    'status' => fn (): string => $statuses[array_rand($statuses)],
                    'reference_month' => $referenceMonth,
                    'due_date' => $dueDate,
                    'payment_method' => fn (): string => $paymentMethods[array_rand($paymentMethods)],
                    'amount' => random_int(100, 5000),
                    'paid_at' => $status === 'paid' ? $dueDate->copy()->addDays(random_int(1, 5)) : null,
                ]);
        }
    }
}
