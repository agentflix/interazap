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
        // Use CASCADE to force drop legacy tables and their constraints in Postgres
        $tables = [
            'ai_agent_tools',
            'ai_autopilot_approvals',
            'ai_autopilot_actions',
            'ai_autopilot_guardrails',
            'ai_autopilot_tools',
            'ai_autopilot_playbooks',
        ];

        foreach ($tables as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table} CASCADE");
        }

        // Recreate them with modern schema

        Schema::create('ai_autopilot_playbooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type', 50)->default('MANUAL');
            $table->unsignedInteger('version')->default(1);
            $table->json('steps')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('ai_autopilot_tools', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->string('handler_class')->nullable();
            $table->json('parameters_schema')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('ai_autopilot_guardrails', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('rule_type', 20)->default('LOG');
            $table->json('conditions')->nullable();
            $table->unsignedInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('ai_autopilot_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('run_id')->index();
            $table->string('action_type', 50);
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->string('guardrail_result', 20)->nullable();
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->foreign('run_id')->references('id')->on('ai_autopilot_runs')->cascadeOnDelete();
        });

        Schema::create('ai_autopilot_approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('run_id')->index();
            $table->string('status', 30)->default('pending');
            $table->json('requested_action')->nullable();
            $table->text('reason')->nullable();
            $table->uuid('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->foreign('run_id')->references('id')->on('ai_autopilot_runs')->cascadeOnDelete();
        });

        Schema::create('ai_agent_tools', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('agent_id');
            $table->uuid('tool_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('ai_agents')->cascadeOnDelete();
            $table->foreign('tool_id')->references('id')->on('ai_autopilot_tools')->cascadeOnDelete();
            $table->unique(['agent_id', 'tool_id']);
        });
    }

    public function down(): void
    {
        $tables = [
            'ai_agent_tools',
            'ai_autopilot_approvals',
            'ai_autopilot_actions',
            'ai_autopilot_guardrails',
            'ai_autopilot_tools',
            'ai_autopilot_playbooks',
        ];

        foreach ($tables as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table} CASCADE");
        }
    }
};
