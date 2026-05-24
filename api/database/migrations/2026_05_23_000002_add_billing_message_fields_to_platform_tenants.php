<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_tenants', 'overage_mode_override')) {
                $table->string('overage_mode_override', 10)->nullable()->after('plan_id');
            }

            if (! Schema::hasColumn('platform_tenants', 'billing_cycle_anchor_day')) {
                $table->smallInteger('billing_cycle_anchor_day')->nullable()->after('overage_mode_override');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_tenants', 'billing_cycle_anchor_day')) {
                $table->dropColumn('billing_cycle_anchor_day');
            }

            if (Schema::hasColumn('platform_tenants', 'overage_mode_override')) {
                $table->dropColumn('overage_mode_override');
            }
        });
    }
};
