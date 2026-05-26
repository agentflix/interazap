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
            if (! Schema::hasColumn('platform_plans', 'cycle_days')) {
                $table->integer('cycle_days')->default(30)->after('price_monthly');
            }

            if (! Schema::hasColumn('platform_plans', 'is_trial')) {
                $table->boolean('is_trial')->default(false)->after('cycle_days');
            }
        });

        // Backfill cycle_days=30 for existing rows
        DB::table('platform_plans')->whereNull('cycle_days')->orWhere('cycle_days', 0)->update(['cycle_days' => 30]);
    }

    public function down(): void
    {
        Schema::table('platform_plans', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_plans', 'is_trial')) {
                $table->dropColumn('is_trial');
            }

            if (Schema::hasColumn('platform_plans', 'cycle_days')) {
                $table->dropColumn('cycle_days');
            }
        });
    }
};
