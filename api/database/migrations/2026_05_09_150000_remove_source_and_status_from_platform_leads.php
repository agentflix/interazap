<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_leads')) {
            return;
        }

        Schema::table('platform_leads', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_leads', 'source')) {
                $table->dropIndex('idx_platform_leads_source');
                $table->dropColumn('source');
            }

            if (Schema::hasColumn('platform_leads', 'status')) {
                $table->dropIndex('idx_platform_leads_status');
                $table->dropColumn('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_leads')) {
            return;
        }

        Schema::table('platform_leads', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_leads', 'source')) {
                $table->string('source', 50)->nullable();
                $table->index('source', 'idx_platform_leads_source');
            }

            if (! Schema::hasColumn('platform_leads', 'status')) {
                $table->string('status', 20)->nullable();
                $table->index('status', 'idx_platform_leads_status');
            }
        });
    }
};
