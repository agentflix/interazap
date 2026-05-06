<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_prompt_plans', function (Blueprint $table): void {
            if (Schema::hasColumn('ai_prompt_plans', 'overage_price_per_1k')) {
                $table->dropColumn('overage_price_per_1k');
            }

            if (Schema::hasColumn('ai_prompt_plans', 'allow_overage')) {
                $table->dropColumn('allow_overage');
            }

            if (Schema::hasColumn('ai_prompt_plans', 'token_limit_monthly')) {
                $table->dropColumn('token_limit_monthly');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_prompt_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_prompt_plans', 'token_limit_monthly')) {
                $table->integer('token_limit_monthly')->nullable()->after('content');
            }

            if (! Schema::hasColumn('ai_prompt_plans', 'allow_overage')) {
                $table->boolean('allow_overage')->default(false)->after('token_limit_monthly');
            }

            if (! Schema::hasColumn('ai_prompt_plans', 'overage_price_per_1k')) {
                $table->decimal('overage_price_per_1k', 10, 6)->nullable()->after('allow_overage');
            }
        });
    }
};
