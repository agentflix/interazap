<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BillingInvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();
        $plans = PlatformPlan::query()->get();

        if ($tenants->isEmpty() || $plans->isEmpty()) {
            $this->command->warn('Tenants or plans not found.');

            return;
        }

        $months = [
            now()->subMonth()->format('Y-m'),
            now()->format('Y-m'),
            now()->addMonth()->format('Y-m'),
        ];

        $defaultTenantCodes = ['AGENTFLX'];

        foreach ($tenants as $tenant) {
            // Skip default system tenant — its plan is managed by PlatformPlanSeeder
            if (in_array($tenant->tenant_code, $defaultTenantCodes, true)) {
                continue;
            }

            foreach ($months as $index => $month) {
                $plan = $plans->random();
                $status = match ($index) {
                    0 => BillingInvoiceStatus::PAID,
                    1 => BillingInvoiceStatus::PENDING,
                    default => BillingInvoiceStatus::DRAFT,
                };

                $invoice = BillingInvoice::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'reference_month' => $month],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'plan_id' => $plan->id,
                        'amount' => $plan->price_monthly,
                        'status' => $status,
                        'due_date' => now()->setDay(10),
                        'paid_at' => $status === BillingInvoiceStatus::PAID ? now()->subDays(5) : null,
                        'payment_method' => $status === BillingInvoiceStatus::PAID ? 'pix' : null,
                    ]
                );

                if ($invoice->status === BillingInvoiceStatus::PAID && ! $invoice->paid_at) {
                    $invoice->paid_at = now()->subDays(5);
                    $invoice->save();
                }
            }
        }

        $this->command->info('Billing invoices created.');
    }
}
