<?php

declare(strict_types=1);

use Domain\Ai\Models\AiUsageLog;
use Domain\Billing\Actions\CalculateAiOverageAction;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;

it('calcula overage de IA para tokens acima do limite mensal', function (): void {
    $plan = PlatformPlan::factory()->create([
        'token_limit_monthly' => 100000,
        'allow_overage' => true,
        'overage_price_per_1k' => 2.00,
    ]);
    $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);

    AiUsageLog::factory()->create([
        'tenant_id' => $tenant->id,
        'input_tokens' => 100000,
        'output_tokens' => 50000,
        'created_at' => '2026-04-15 10:00:00',
    ]);

    $result = app(CalculateAiOverageAction::class)->execute((string) $tenant->id, '2026-04');

    expect($result['total_tokens'])->toBe(150000)
        ->and($result['overage_tokens'])->toBe(50000)
        ->and($result['overage_amount'])->toBe(100.0)
        ->and($result['overage_applied'])->toBeTrue();
});

it('gera fatura com mensalidade e overage', function (): void {
    \Illuminate\Support\Facades\Date::setTestNow('2026-05-01 06:00:00');

    $plan = PlatformPlan::factory()->create([
        'price_monthly' => 297.00,
        'token_limit_monthly' => 100000,
        'allow_overage' => true,
        'overage_price_per_1k' => 2.00,
    ]);
    $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);

    AiUsageLog::factory()->create([
        'tenant_id' => $tenant->id,
        'input_tokens' => 120000,
        'output_tokens' => 30000,
        'created_at' => '2026-04-20 12:00:00',
    ]);

    $this->artisan('billing:generate-monthly-invoices', ['reference_month' => '2026-04'])
        ->assertSuccessful();

    $invoice = BillingInvoice::query()
        ->where('tenant_id', $tenant->id)
        ->where('reference_month', '2026-04')
        ->firstOrFail();

    expect((float) $invoice->amount)->toBe(397.0)
        ->and($invoice->status)->toBe(BillingInvoiceStatus::DRAFT)
        ->and((float) $invoice->metadata['base_price'])->toBe(297.0)
        ->and($invoice->metadata['overage_applied'])->toBeTrue()
        ->and($invoice->metadata['overage_tokens'])->toBe(50000)
        ->and((float) $invoice->metadata['overage_amount'])->toBe(100.0);

    \Illuminate\Support\Facades\Date::setTestNow();
});

it('nao cria faturas em dry run', function (): void {
    $plan = PlatformPlan::factory()->create(['price_monthly' => 97.00]);
    PlatformTenant::factory()->create(['plan_id' => $plan->id]);

    $this->artisan('billing:generate-monthly-invoices', [
        'reference_month' => '2026-04',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(BillingInvoice::query()->where('reference_month', '2026-04')->count())->toBe(0);
});

it('nao duplica fatura para o mesmo tenant e mes de referencia', function (): void {
    $plan = PlatformPlan::factory()->create(['price_monthly' => 97.00]);
    $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);

    BillingInvoice::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'reference_month' => '2026-04',
        'amount' => 97.00,
    ]);

    $this->artisan('billing:generate-monthly-invoices', ['reference_month' => '2026-04'])
        ->assertSuccessful();

    expect(BillingInvoice::query()
        ->where('tenant_id', $tenant->id)
        ->where('reference_month', '2026-04')
        ->count())->toBe(1);
});

it('gera fatura sem overage quando nao ha excedente', function (): void {
    $plan = PlatformPlan::factory()->create([
        'price_monthly' => 97.00,
        'token_limit_monthly' => 100000,
        'allow_overage' => true,
        'overage_price_per_1k' => 2.00,
    ]);
    $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);

    AiUsageLog::factory()->create([
        'tenant_id' => $tenant->id,
        'input_tokens' => 40000,
        'output_tokens' => 10000,
        'created_at' => '2026-04-20 12:00:00',
    ]);

    $this->artisan('billing:generate-monthly-invoices', ['reference_month' => '2026-04'])
        ->assertSuccessful();

    $invoice = BillingInvoice::query()
        ->where('tenant_id', $tenant->id)
        ->where('reference_month', '2026-04')
        ->firstOrFail();

    expect((float) $invoice->amount)->toBe(97.0)
        ->and($invoice->metadata['overage_applied'])->toBeFalse()
        ->and((float) $invoice->metadata['overage_amount'])->toBe(0.0);
});
