<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_skills', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('agent_id');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('ai_agents')->cascadeOnDelete();
            $table->unique(['agent_id', 'name']);
            $table->index(['tenant_id', 'agent_id']);
        });

        Schema::create('ai_agent_delegations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('source_agent_id');
            $table->uuid('target_agent_id');
            $table->unsignedTinyInteger('max_depth')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->foreign('source_agent_id')->references('id')->on('ai_agents')->cascadeOnDelete();
            $table->foreign('target_agent_id')->references('id')->on('ai_agents')->cascadeOnDelete();
            $table->unique(['source_agent_id', 'target_agent_id']);
            $table->index(['tenant_id', 'source_agent_id']);
        });

        Schema::create('ai_agent_files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('agent_id');
            $table->string('slug', 120);
            $table->longText('content')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('ai_agents')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('auth_users')->nullOnDelete();
            $table->unique(['agent_id', 'slug']);
            $table->index(['tenant_id', 'agent_id']);
        });

        Schema::create('ai_agent_tools', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('agent_id');
            $table->uuid('tool_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('ai_agents')->cascadeOnDelete();
            $table->foreign('tool_id')->references('id')->on('ai_autopilot_tools')->cascadeOnDelete();
            $table->unique(['agent_id', 'tool_id']);
            $table->index(['tenant_id', 'agent_id']);
        });

        Schema::create('ai_agent_triggers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('agent_id');
            $table->string('type', 50);
            $table->json('config')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('ai_agents')->cascadeOnDelete();
            $table->index(['tenant_id', 'agent_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('ai_conversation_summaries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('ticket_id');
            $table->longText('summary');
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->foreign('ticket_id')->references('id')->on('chat_tickets')->cascadeOnDelete();
            $table->unique(['tenant_id', 'ticket_id']);
            $table->index(['tenant_id', 'generated_at']);
        });

        Schema::create('ai_agent_channels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('agent_id');
            $table->string('channel', 40);
            $table->string('external_ref', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('ai_agents')->cascadeOnDelete();
            $table->unique(['agent_id', 'channel', 'external_ref']);
            $table->index(['tenant_id', 'agent_id']);
        });

        Schema::table('ai_agents', function (Blueprint $table): void {
            $table->string('scope', 30)->default('tenant');
            $table->uuid('parent_agent_id')->nullable();
            $table->string('classifier_model', 50)->nullable();
            $table->unsignedInteger('token_budget_input')->default(0);
            $table->unsignedInteger('token_budget_output')->default(0);
            $table->text('fallback_message')->nullable();
            $table->json('metadata')->nullable();
            $table->string('voice_response_mode', 20)->default('text');
            $table->string('stt_model', 50)->nullable();
            $table->string('stt_language', 16)->nullable();
            $table->string('tts_model', 50)->nullable();
            $table->string('tts_voice', 50)->nullable();
            $table->decimal('tts_speed', 4, 2)->default(1.00);

            $table->foreign('parent_agent_id')->references('id')->on('ai_agents')->nullOnDelete();
            $table->index(['tenant_id', 'scope']);
        });

        Schema::table('ai_autopilot_runs', function (Blueprint $table): void {
            $table->uuid('parent_run_id')->nullable();
            $table->unsignedTinyInteger('delegation_depth')->default(0);
            $table->unsignedInteger('cached_prompt_tokens')->default(0);
            $table->boolean('streaming_enabled')->default(false);
            $table->string('classifier_result', 30)->nullable();
            $table->unsignedInteger('classifier_tokens')->nullable();

            $table->foreign('parent_run_id')->references('id')->on('ai_autopilot_runs')->nullOnDelete();
            $table->index(['tenant_id', 'parent_run_id']);
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->text('transcription')->nullable();
            $table->unsignedInteger('audio_duration_ms')->nullable();
            $table->string('audio_mime_type', 120)->nullable();
        });

        Schema::table('ai_usage_logs', function (Blueprint $table): void {
            $table->unsignedInteger('cached_prompt_tokens')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table): void {
            $table->dropColumn('cached_prompt_tokens');
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn(['transcription', 'audio_duration_ms', 'audio_mime_type']);
        });

        Schema::table('ai_autopilot_runs', function (Blueprint $table): void {
            $table->dropForeign(['parent_run_id']);
            $table->dropColumn([
                'parent_run_id',
                'delegation_depth',
                'cached_prompt_tokens',
                'streaming_enabled',
                'classifier_result',
                'classifier_tokens',
            ]);
        });

        Schema::table('ai_agents', function (Blueprint $table): void {
            $table->dropForeign(['parent_agent_id']);
            $table->dropColumn([
                'scope',
                'parent_agent_id',
                'classifier_model',
                'token_budget_input',
                'token_budget_output',
                'fallback_message',
                'metadata',
                'voice_response_mode',
                'stt_model',
                'stt_language',
                'tts_model',
                'tts_voice',
                'tts_speed',
            ]);
        });

        Schema::dropIfExists('ai_agent_channels');
        Schema::dropIfExists('ai_conversation_summaries');
        Schema::dropIfExists('ai_agent_triggers');
        Schema::dropIfExists('ai_agent_tools');
        Schema::dropIfExists('ai_agent_files');
        Schema::dropIfExists('ai_agent_delegations');
        Schema::dropIfExists('ai_agent_skills');
    }
};
