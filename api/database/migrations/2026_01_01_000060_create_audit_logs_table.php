<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit Domain Tables - Consolidated Migration
 *
 * Creates audit logging infrastructure:
 * - audit_logs: Activity tracking with proper structure
 *
 * Note: This table can grow large. Consider partitioning by month
 * and implementing data retention policies (e.g., 12 months).
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // AUDIT_LOGS - Activity tracking
        // =====================================================================
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('user_type')->nullable();
            $table->string('event', 100); // created, updated, deleted, login, etc.
            $table->string('auditable_type'); // Model class name
            $table->string('auditable_id'); // Model ID (string for flexibility)
            $table->text('old_values')->nullable(); // JSON encoded
            $table->text('new_values')->nullable(); // JSON encoded
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            // Performance indexes
            $table->index(['tenant_id', 'event']);
            $table->index(['tenant_id', 'auditable_type', 'auditable_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at'); // For data retention cleanup
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
