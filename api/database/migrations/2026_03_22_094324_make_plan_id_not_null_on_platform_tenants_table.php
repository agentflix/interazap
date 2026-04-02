<?php

declare(strict_types=1);

use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Garantir que todos os tenants tenham um plano antes de tornar NOT NULL
        $starterPlan = PlatformPlan::query()->where('slug', 'starter')->first();

        if ($starterPlan) {
            PlatformTenant::query()->whereNull('plan_id')->update(['plan_id' => $starterPlan->id]);
        }

        // 2. Alterar a coluna para NOT NULL
        Schema::table('platform_tenants', function (Blueprint $table) {
            $table->uuid('plan_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table) {
            $table->uuid('plan_id')->nullable(true)->change();
        });
    }
};
