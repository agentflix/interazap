<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create ai_autopilot_trigger_logs table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_autopilot_trigger_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('trigger_type', 50)->index();
            $table->string('source_id', 100)->nullable();
            $table->string('source_type', 100)->nullable();
            $table->uuid('playbook_id')->nullable()->index();
            $table->uuid('run_id')->nullable()->index();
            $table->string('status', 30);
            $table->json('context')->nullable();
            $table->text('skip_reason')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'trigger_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_autopilot_trigger_logs');
    }
};
