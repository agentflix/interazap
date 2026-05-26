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
            if (! Schema::hasColumn('platform_tenants', 'has_used_trial')) {
                $table->boolean('has_used_trial')->default(false)->after('billing_cycle_anchor_day');
            }

            if (! Schema::hasColumn('platform_tenants', 'payment_method_token')) {
                $table->string('payment_method_token', 255)->nullable()->after('has_used_trial');
            }

            if (! Schema::hasColumn('platform_tenants', 'payment_method_brand')) {
                $table->string('payment_method_brand', 20)->nullable()->after('payment_method_token');
            }

            if (! Schema::hasColumn('platform_tenants', 'payment_method_last4')) {
                $table->string('payment_method_last4', 4)->nullable()->after('payment_method_brand');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_tenants', 'payment_method_last4')) {
                $table->dropColumn('payment_method_last4');
            }

            if (Schema::hasColumn('platform_tenants', 'payment_method_brand')) {
                $table->dropColumn('payment_method_brand');
            }

            if (Schema::hasColumn('platform_tenants', 'payment_method_token')) {
                $table->dropColumn('payment_method_token');
            }

            if (Schema::hasColumn('platform_tenants', 'has_used_trial')) {
                $table->dropColumn('has_used_trial');
            }
        });
    }
};
