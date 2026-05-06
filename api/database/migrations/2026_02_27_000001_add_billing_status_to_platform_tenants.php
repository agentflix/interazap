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
        if (Schema::hasColumn('platform_tenants', 'billing_status')) {
            return;
        }

        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->string('billing_status', 20)->default('active')->after('is_active');
            $table->timestamp('billing_locked_at')->nullable()->after('billing_status');
            $table->string('billing_lock_reason', 100)->nullable()->after('billing_locked_at');
            $table->date('billing_grace_deadline')->nullable()->after('billing_lock_reason');
            $table->date('billing_purge_deadline')->nullable()->after('billing_grace_deadline');
            $table->timestamp('last_collection_sent_at')->nullable()->after('billing_purge_deadline');
            $table->integer('collection_count')->default(0)->after('last_collection_sent_at');

            $table->index('billing_status');
        });

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_tenants_billing_purge_deadline_not_null
            ON platform_tenants (billing_purge_deadline)
            WHERE billing_purge_deadline IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_tenants_billing_purge_deadline_not_null');

        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->dropIndex(['billing_status']);
            $table->dropColumn([
                'billing_status',
                'billing_locked_at',
                'billing_lock_reason',
                'billing_grace_deadline',
                'billing_purge_deadline',
                'last_collection_sent_at',
                'collection_count',
            ]);
        });
    }
};
