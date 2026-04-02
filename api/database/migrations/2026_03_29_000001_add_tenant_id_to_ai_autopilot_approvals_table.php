<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-013-S1-SEC / API-SEC-003
 * Adiciona tenant_id à tabela ai_autopilot_approvals para garantir isolamento de tenant.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificação defensiva: a coluna pode já existir em ambientes onde migrations
        // anteriores (2026-03-04) já a criaram. Aplica apenas o delta necessário.
        if (! Schema::hasColumn('ai_autopilot_approvals', 'tenant_id')) {
            Schema::table('ai_autopilot_approvals', function (Blueprint $table): void {
                $table->uuid('tenant_id')->nullable()->after('id');
                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->cascadeOnDelete();
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('ai_autopilot_approvals', 'tenant_id')) {
            Schema::table('ai_autopilot_approvals', function (Blueprint $table): void {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
