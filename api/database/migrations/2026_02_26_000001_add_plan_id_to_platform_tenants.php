<?php

declare(strict_types=1);

use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->uuid('plan_id')->nullable()->after('segment_id');

            $table->foreign('plan_id')
                ->references('id')
                ->on('platform_plans')
                ->nullOnDelete();

            $table->index(['plan_id']);
        });

        PlatformTenant::query()
            ->whereNull('plan_id')
            ->chunkById(100, function ($tenants): void {
                foreach ($tenants as $tenant) {
                    /** @var PlatformTenant $tenant */
                    $invoice = BillingInvoice::query()
                        ->withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->whereNotNull('plan_id')
                        ->whereIn('status', [
                            BillingInvoiceStatus::PAID->value,
                            BillingInvoiceStatus::PENDING->value,
                            BillingInvoiceStatus::OVERDUE->value,
                            BillingInvoiceStatus::DRAFT->value,
                        ])
                        ->latest('paid_at')
                        ->latest('due_date')->latest()
                        ->first();

                    if ($invoice?->plan_id !== null) {
                        $tenant->plan_id = $invoice->plan_id;
                        $tenant->save();
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->dropForeign(['plan_id']);
            $table->dropIndex(['plan_id']);
            $table->dropColumn('plan_id');
        });
    }
};
