<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_autopilot_playbooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->json('steps')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('ai_autopilot_tools', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->json('parameters_schema')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('ai_autopilot_guardrails', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('rule_type')->default('LOG_ONLY');
            $table->json('conditions')->nullable();
            $table->unsignedInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('ai_autopilot_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('playbook_id')->constrained('ai_autopilot_playbooks')->cascadeOnDelete();
            $table->string('status')->default('running');
            $table->unsignedInteger('playbook_version')->default(1);
            $table->json('input_context')->nullable();
            $table->json('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('ai_autopilot_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('ai_autopilot_runs')->cascadeOnDelete();
            $table->string('action_type');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->string('guardrail_result')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
            $table->index(['run_id', 'order']);
        });

        Schema::create('ai_autopilot_approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('ai_autopilot_runs')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->json('requested_action')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
            $table->index(['run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_autopilot_approvals');
        Schema::dropIfExists('ai_autopilot_actions');
        Schema::dropIfExists('ai_autopilot_runs');
        Schema::dropIfExists('ai_autopilot_guardrails');
        Schema::dropIfExists('ai_autopilot_tools');
        Schema::dropIfExists('ai_autopilot_playbooks');
    }
};
