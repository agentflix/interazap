<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_plans', 'token_limit_monthly')) {
                $table->integer('token_limit_monthly')->nullable()->after('ai_enabled');
            }

            if (! Schema::hasColumn('platform_plans', 'allow_overage')) {
                $table->boolean('allow_overage')->default(false)->after('token_limit_monthly');
            }

            if (! Schema::hasColumn('platform_plans', 'overage_price_per_1k')) {
                $table->decimal('overage_price_per_1k', 10, 2)->nullable()->after('allow_overage');
            }
        });

        if (
            Schema::hasTable('ai_prompt_plans')
            && Schema::hasColumn('ai_prompt_plans', 'token_limit_monthly')
            && Schema::hasColumn('ai_prompt_plans', 'allow_overage')
            && Schema::hasColumn('ai_prompt_plans', 'overage_price_per_1k')
        ) {
            DB::statement(<<<'SQL'
                UPDATE platform_plans pp
                SET token_limit_monthly = ap.token_limit_monthly,
                    allow_overage = ap.allow_overage,
                    overage_price_per_1k = ap.overage_price_per_1k
                FROM ai_prompt_plans ap
                WHERE ap.plan_id = pp.id
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('platform_plans', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_plans', 'overage_price_per_1k')) {
                $table->dropColumn('overage_price_per_1k');
            }

            if (Schema::hasColumn('platform_plans', 'allow_overage')) {
                $table->dropColumn('allow_overage');
            }

            if (Schema::hasColumn('platform_plans', 'token_limit_monthly')) {
                $table->dropColumn('token_limit_monthly');
            }
        });
    }
};
