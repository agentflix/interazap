<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_tenants', 'billing_webhook_token')) {
                $table->uuid('billing_webhook_token')->nullable()->after('primary_email');
            }
        });

        // Popular tokens para linhas existentes antes de forçar NOT NULL
        DB::table('platform_tenants')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($tenants): void {
                foreach ($tenants as $tenant) {
                    DB::table('platform_tenants')
                        ->where('id', $tenant->id)
                        ->update(['billing_webhook_token' => Str::uuid()->toString()]);
                }
            }, 'id');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE platform_tenants ALTER COLUMN billing_webhook_token SET NOT NULL');
        }
        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->unique('billing_webhook_token', 'platform_tenants_billing_webhook_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_tenants', 'billing_webhook_token')) {
                $table->dropUnique('platform_tenants_billing_webhook_token_unique');
                $table->dropColumn('billing_webhook_token');
            }
        });
    }
};
