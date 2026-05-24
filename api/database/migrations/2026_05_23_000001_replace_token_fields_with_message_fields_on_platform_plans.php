<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_plans', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_plans', 'token_limit_monthly')) {
                $table->dropColumn('token_limit_monthly');
            }

            if (Schema::hasColumn('platform_plans', 'allow_overage')) {
                $table->dropColumn('allow_overage');
            }

            if (Schema::hasColumn('platform_plans', 'overage_price_per_1k')) {
                $table->dropColumn('overage_price_per_1k');
            }

            if (! Schema::hasColumn('platform_plans', 'message_limit_monthly')) {
                $table->integer('message_limit_monthly')->default(0)->after('ai_enabled');
            }

            if (! Schema::hasColumn('platform_plans', 'overage_mode')) {
                $table->string('overage_mode', 10)->default('stop')->after('message_limit_monthly');
            }

            if (! Schema::hasColumn('platform_plans', 'overage_price_per_message')) {
                $table->decimal('overage_price_per_message', 10, 4)->nullable()->after('overage_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_plans', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_plans', 'overage_price_per_message')) {
                $table->dropColumn('overage_price_per_message');
            }

            if (Schema::hasColumn('platform_plans', 'overage_mode')) {
                $table->dropColumn('overage_mode');
            }

            if (Schema::hasColumn('platform_plans', 'message_limit_monthly')) {
                $table->dropColumn('message_limit_monthly');
            }

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
    }
};
