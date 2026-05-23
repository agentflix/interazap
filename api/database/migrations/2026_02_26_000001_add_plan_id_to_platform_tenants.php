<?php

declare(strict_types=1);

use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('platform_tenants', 'plan_id')) {
            return;
        }

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
        if (! Schema::hasTable('platform_tenants') || ! Schema::hasColumn('platform_tenants', 'plan_id')) {
            return;
        }

        // Base schema already contains plan_id with canonical index name.
        $hasCanonicalIndex = DB::selectOne("
            SELECT 1
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND tablename = 'platform_tenants'
              AND indexname = 'idx_platform_tenants_plan_id'
            LIMIT 1
        ");

        if ($hasCanonicalIndex) {
            return;
        }

        DB::statement('ALTER TABLE platform_tenants DROP CONSTRAINT IF EXISTS platform_tenants_plan_id_foreign');
        DB::statement('DROP INDEX IF EXISTS platform_tenants_plan_id_index');

        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->dropColumn('plan_id');
        });
    }
};
