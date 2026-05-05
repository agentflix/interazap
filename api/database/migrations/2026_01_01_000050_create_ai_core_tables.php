<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Cria as tabelas core do contexto AI (Autopilot, Agents, Usage, Notifications).
 *
 * Tabelas criadas:
 * - ai_autopilot_playbooks: Playbooks de automação do Autopilot
 * - ai_autopilot_tools: Ferramentas disponíveis para o Autopilot
 * - ai_autopilot_guardrails: Regras de guardrail do Autopilot
 * - ai_autopilot_runs: Execuções de playbooks
 * - ai_autopilot_actions: Ações executadas dentro de uma run
 * - ai_autopilot_approvals: Aprovações manuais de ações
 * - ai_model_pricings: Preços por modelo de IA
 * - ai_usage_logs: Logs de uso de modelos de IA
 * - ai_seller_notifications: Notificações inteligentes para vendedores
 * - ai_post_sale_schedules: Agendamentos pós-venda
 * - ai_agents: Agentes de IA configuráveis
 * - ai_agent_skills: Skills vinculadas a agentes
 * - ai_agent_delegations: Regras de delegação entre agentes
 * - ai_agent_files: Arquivos de contexto dos agentes
 * - ai_agent_tools: Pivot agente x ferramenta
 * - ai_agent_triggers: Gatilhos de ativação dos agentes
 * - ai_conversation_summaries: Resumos de conversas
 * - ai_agent_channels: Canais vinculados aos agentes
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_autopilot_playbooks')) {
            return;
        }

        $this->createAutopilotPlaybooks();
        $this->createAutopilotTools();
        $this->createAutopilotGuardrails();
        $this->createAutopilotRuns();
        $this->createAutopilotActions();
        $this->createAutopilotApprovals();
        $this->createModelPricings();
        $this->createUsageLogs();
        $this->createSellerNotifications();
        $this->createPostSaleSchedules();
        $this->createAgents();
        $this->createAgentSkills();
        $this->createAgentDelegations();
        $this->createAgentFiles();
        $this->createAgentTools();
        $this->createAgentTriggers();
        $this->createConversationSummaries();
        $this->createAgentChannels();
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_channels');
        Schema::dropIfExists('ai_conversation_summaries');
        Schema::dropIfExists('ai_agent_triggers');
        Schema::dropIfExists('ai_agent_tools');
        Schema::dropIfExists('ai_agent_files');
        Schema::dropIfExists('ai_agent_delegations');
        Schema::dropIfExists('ai_agent_skills');
        Schema::dropIfExists('ai_agents');
        Schema::dropIfExists('ai_post_sale_schedules');
        Schema::dropIfExists('ai_seller_notifications');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_model_pricings');
        Schema::dropIfExists('ai_autopilot_approvals');
        Schema::dropIfExists('ai_autopilot_actions');
        Schema::dropIfExists('ai_autopilot_runs');
        Schema::dropIfExists('ai_autopilot_guardrails');
        Schema::dropIfExists('ai_autopilot_tools');
        Schema::dropIfExists('ai_autopilot_playbooks');
    }

    private function createAutopilotPlaybooks(): void
    {
        Schema::create('ai_autopilot_playbooks', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do playbook (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono do playbook');
            $table->string('name', 255)->comment('Nome do playbook');
            $table->text('description')->nullable()->comment('Descrição do playbook');
            $table->string('trigger_type', 20)->default('manual')->comment('Tipo de gatilho (manual/auto/scheduled)');
            $table->integer('version')->default(1)->comment('Versão do playbook');
            $table->jsonb('steps')->nullable()->comment('Passos do playbook em JSON');
            $table->jsonb('metadata')->nullable()->comment('Metadados adicionais');
            $table->boolean('is_active')->default(true)->comment('Indica se o playbook está ativo');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id', 'fk_ai_autopilot_playbooks_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->index(['tenant_id', 'is_active'], 'idx_ai_autopilot_playbooks_tenant_active');
            $table->index(['trigger_type'], 'idx_ai_autopilot_playbooks_trigger_type');
        });
    }

    private function createAutopilotTools(): void
    {
        Schema::create('ai_autopilot_tools', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único da ferramenta (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono da ferramenta');
            $table->string('name', 255)->comment('Nome técnico da ferramenta');
            $table->string('display_name', 255)->comment('Nome amigável da ferramenta');
            $table->text('description')->nullable()->comment('Descrição da ferramenta');
            $table->string('handler_class', 255)->nullable()->comment('Classe PHP que executa a ferramenta');
            $table->jsonb('parameters_schema')->nullable()->comment('Schema JSON dos parâmetros aceitos');
            $table->boolean('is_system')->default(false)->comment('Indica se é ferramenta do sistema');
            $table->boolean('is_active')->default(true)->comment('Indica se a ferramenta está ativa');
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_ai_autopilot_tools_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->index(['tenant_id', 'is_active'], 'idx_ai_autopilot_tools_tenant_active');
            $table->index(['is_system'], 'idx_ai_autopilot_tools_is_system');
        });
    }

    private function createAutopilotGuardrails(): void
    {
        Schema::create('ai_autopilot_guardrails', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único da guardrail (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono da guardrail');
            $table->string('name', 255)->comment('Nome da guardrail');
            $table->text('description')->nullable()->comment('Descrição da regra de guardrail');
            $table->string('rule_type', 50)->comment('Tipo de regra (block/allow/warn)');
            $table->jsonb('conditions')->comment('Condições da regra em JSON');
            $table->integer('priority')->default(0)->comment('Prioridade de avaliação (maior = primeiro)');
            $table->boolean('is_active')->default(true)->comment('Indica se a guardrail está ativa');
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_ai_autopilot_guardrails_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->index(['tenant_id', 'is_active'], 'idx_ai_autopilot_guardrails_tenant_active');
            $table->index(['rule_type', 'priority'], 'idx_ai_autopilot_guardrails_type_priority');
        });
    }

    private function createAutopilotRuns(): void
    {
        Schema::create('ai_autopilot_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único da execução (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono da execução');
            $table->uuid('playbook_id')->nullable()->comment('Playbook executado');
            $table->string('status', 20)->default('running')->comment('Status da execução (running/completed/failed/cancelled)');
            $table->integer('playbook_version')->nullable()->comment('Versão do playbook utilizada');
            $table->json('input_context')->nullable()->comment('Contexto de entrada da execução');
            $table->json('output')->nullable()->comment('Resultado/output da execução');
            $table->timestamp('started_at', 0)->nullable()->comment('Quando a execução iniciou');
            $table->timestamp('completed_at', 0)->nullable()->comment('Quando a execução finalizou');
            $table->timestamps(0);
            $table->uuid('parent_run_id')->nullable()->comment('Run pai em caso de delegação');
            $table->smallInteger('delegation_depth')->default(0)->comment('Profundidade de delegação');
            $table->integer('cached_prompt_tokens')->default(0)->comment('Tokens de prompt em cache');
            $table->boolean('streaming_enabled')->default(false)->comment('Indica se streaming estava ativo');
            $table->string('classifier_result', 30)->nullable()->comment('Resultado do classificador');
            $table->integer('classifier_tokens')->nullable()->comment('Tokens usados no classificador');

            $table->foreign('tenant_id', 'fk_ai_autopilot_runs_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('playbook_id', 'fk_ai_autopilot_runs_playbook_id')
                ->references('id')
                ->on('ai_autopilot_playbooks');
            // Self-reference FK parent_run_id adicionada em migration posterior
            $table->index(['tenant_id', 'status'], 'idx_ai_autopilot_runs_tenant_status');
            $table->index(['playbook_id'], 'idx_ai_autopilot_runs_playbook_id');
            $table->index(['parent_run_id'], 'idx_ai_autopilot_runs_parent_run_id');
            $table->index(['started_at'], 'idx_ai_autopilot_runs_started_at');
        });
    }

    private function createAutopilotActions(): void
    {
        Schema::create('ai_autopilot_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único da ação (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono da ação');
            $table->uuid('run_id')->comment('Execução (run) que contém esta ação');
            $table->string('action_type', 50)->comment('Tipo de ação (send_message/call_tool/wait)');
            $table->jsonb('input')->nullable()->comment('Input da ação');
            $table->jsonb('output')->nullable()->comment('Output da ação');
            $table->jsonb('guardrail_result')->nullable()->comment('Resultado da avaliação de guardrail');
            $table->integer('order')->default(0)->comment('Ordem de execução dentro da run');
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_ai_autopilot_actions_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('run_id', 'fk_ai_autopilot_actions_run_id')
                ->references('id')
                ->on('ai_autopilot_runs');
            $table->index(['tenant_id', 'run_id'], 'idx_ai_autopilot_actions_tenant_run');
            $table->index(['run_id', 'order'], 'idx_ai_autopilot_actions_run_order');
        });
    }

    private function createAutopilotApprovals(): void
    {
        Schema::create('ai_autopilot_approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único da aprovação (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono da aprovação');
            $table->uuid('run_id')->comment('Execução que solicitou aprovação');
            $table->string('status', 20)->default('pending')->comment('Status (pending/approved/rejected)');
            $table->jsonb('requested_action')->nullable()->comment('Ação solicitada para aprovação');
            $table->uuid('approved_by')->nullable()->comment('Usuário que aprovou');
            $table->timestamp('approved_at')->nullable()->comment('Quando foi aprovado');
            $table->timestamp('rejected_at')->nullable()->comment('Quando foi rejeitado');
            $table->text('rejected_reason')->nullable()->comment('Motivo da rejeição');
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_ai_autopilot_approvals_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('run_id', 'fk_ai_autopilot_approvals_run_id')
                ->references('id')
                ->on('ai_autopilot_runs');
            $table->foreign('approved_by', 'fk_ai_autopilot_approvals_approved_by')
                ->references('id')
                ->on('auth_users');
            $table->index(['tenant_id', 'status'], 'idx_ai_autopilot_approvals_tenant_status');
            $table->index(['run_id'], 'idx_ai_autopilot_approvals_run_id');
            $table->index(['approved_by'], 'idx_ai_autopilot_approvals_approved_by');
        });
    }

    private function createModelPricings(): void
    {
        Schema::create('ai_model_pricings', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do preço (UUID)');
            $table->string('provider', 50)->comment('Provedor (openai/anthropic/gemini/minimax)');
            $table->string('model_name', 100)->comment('Nome técnico do modelo');
            $table->string('display_name', 150)->nullable()->comment('Nome amigável do modelo');
            $table->decimal('input_cost_per_1m', 10, 4)->default(0)->comment('Custo por 1M tokens de entrada');
            $table->decimal('output_cost_per_1m', 10, 4)->default(0)->comment('Custo por 1M tokens de saída');
            $table->integer('max_context_tokens')->nullable()->comment('Máximo de tokens de contexto');
            $table->integer('max_output_tokens')->nullable()->comment('Máximo de tokens de saída');
            $table->boolean('is_active')->default(true)->comment('Indica se o preço está ativo');
            $table->timestamp('pricing_effective_date', 0)->nullable()->comment('Data de vigência do preço');
            $table->text('notes')->nullable()->comment('Observações sobre o preço');
            $table->timestamps(0);

            $table->unique(['provider', 'model_name'], 'idx_ai_model_pricings_provider_model');
            $table->index(['is_active'], 'idx_ai_model_pricings_is_active');
            $table->index(['pricing_effective_date'], 'idx_ai_model_pricings_effective_date');
        });
    }

    private function createUsageLogs(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do log (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono do log');
            $table->uuid('user_id')->nullable()->comment('Usuário que consumiu os tokens');
            $table->uuid('ai_model_pricing_id')->nullable()->comment('Preço do modelo utilizado');
            $table->string('model_name', 100)->comment('Nome do modelo usado');
            $table->string('provider', 50)->nullable()->comment('Provedor do modelo');
            $table->integer('input_tokens')->default(0)->comment('Tokens de entrada consumidos');
            $table->integer('output_tokens')->default(0)->comment('Tokens de saída consumidos');
            $table->decimal('input_cost', 12, 6)->default(0)->comment('Custo de entrada calculado');
            $table->decimal('output_cost', 12, 6)->default(0)->comment('Custo de saída calculado');
            $table->string('request_id', 100)->nullable()->comment('ID da requisição externa');
            $table->string('feature', 100)->nullable()->comment('Feature que usou (autopilot/rag/chat)');
            $table->integer('latency_ms')->nullable()->comment('Latência da requisição em ms');
            $table->string('usable_type', 255)->nullable()->comment('Tipo da entidade relacionada (Morph)');
            $table->uuid('usable_id')->nullable()->comment('ID da entidade relacionada (Morph)');
            $table->jsonb('metadata')->nullable()->comment('Metadados adicionais');
            $table->timestamps(0);
            $table->integer('cached_prompt_tokens')->default(0)->comment('Tokens de prompt em cache');

            $table->foreign('tenant_id', 'fk_ai_usage_logs_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('user_id', 'fk_ai_usage_logs_user_id')
                ->references('id')
                ->on('auth_users');
            $table->foreign('ai_model_pricing_id', 'fk_ai_usage_logs_pricing_id')
                ->references('id')
                ->on('ai_model_pricings');
            $table->index(['tenant_id', 'created_at'], 'idx_ai_usage_logs_tenant_created');
            $table->index(['user_id'], 'idx_ai_usage_logs_user_id');
            $table->index(['ai_model_pricing_id'], 'idx_ai_usage_logs_pricing_id');
            $table->index(['request_id'], 'idx_ai_usage_logs_request_id');
            $table->index(['feature'], 'idx_ai_usage_logs_feature');
            $table->index(['usable_type', 'usable_id'], 'idx_ai_usage_logs_usable_morph');
        });
    }

    private function createSellerNotifications(): void
    {
        Schema::create('ai_seller_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único da notificação (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono da notificação');
            $table->uuid('seller_id')->comment('Vendedor destinatário');
            $table->string('notifiable_type', 255)->nullable()->comment('Tipo da entidade notificada (Morph)');
            $table->uuid('notifiable_id')->nullable()->comment('ID da entidade notificada (Morph)');
            $table->text('message')->comment('Mensagem da notificação');
            $table->string('reason', 50)->comment('Motivo (lead_score/follow_up)');
            $table->string('channel', 20)->default('email')->comment('Canal de entrega');
            $table->string('priority', 20)->default('normal')->comment('Prioridade da notificação');
            $table->smallInteger('attempts')->default(0)->comment('Tentativas de envio');
            $table->timestamp('scheduled_at', 0)->nullable()->comment('Quando foi agendado');
            $table->timestamp('delivered_at', 0)->nullable()->comment('Quando foi entregue');
            $table->timestamp('failed_at', 0)->nullable()->comment('Quando falhou');
            $table->text('error_message')->nullable()->comment('Erro no envio');
            $table->jsonb('metadata')->nullable()->comment('Metadados adicionais');
            $table->timestamps(0);

            $table->foreign('tenant_id', 'fk_ai_seller_notifications_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('seller_id', 'fk_ai_seller_notifications_seller_id')
                ->references('id')
                ->on('auth_users');
            $table->index(['tenant_id', 'seller_id'], 'idx_ai_seller_notifications_tenant_seller');
            $table->index(['seller_id', 'delivered_at'], 'idx_ai_seller_notifications_seller_delivered');
            $table->index(['notifiable_type', 'notifiable_id'], 'idx_ai_seller_notifications_notifiable_morph');
            $table->index(['status' => 'scheduled_at'], 'idx_ai_seller_notifications_scheduled_at');
            $table->index(['priority'], 'idx_ai_seller_notifications_priority');
        });
    }

    private function createPostSaleSchedules(): void
    {
        Schema::create('ai_post_sale_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do agendamento (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono do agendamento');
            $table->uuid('negotiation_id')->comment('Negociação relacionada');
            $table->uuid('ticket_id')->nullable()->comment('Ticket de chat relacionado');
            $table->string('schedule_type', 20)->comment('Tipo de agendamento (follow_up/nps/renewal)');
            $table->timestamp('sale_date', 0)->comment('Data da venda');
            $table->timestamp('scheduled_at', 0)->comment('Quando deve ser executado');
            $table->string('status', 20)->default('pending')->comment('Status do agendamento');
            $table->timestamp('sent_at', 0)->nullable()->comment('Quando foi enviado');
            $table->string('message_id', 100)->nullable()->comment('Mensagem enviada');
            $table->text('error_message')->nullable()->comment('Erro no envio');
            $table->smallInteger('attempts')->default(0)->comment('Tentativas de envio');
            $table->text('custom_message')->nullable()->comment('Mensagem customizada');
            $table->jsonb('metadata')->nullable()->comment('Metadados adicionais');
            $table->timestamps(0);

            $table->foreign('tenant_id', 'fk_ai_post_sale_schedules_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('negotiation_id', 'fk_ai_post_sale_schedules_negotiation_id')
                ->references('id')
                ->on('crm_negotiations');
            $table->foreign('ticket_id', 'fk_ai_post_sale_schedules_ticket_id')
                ->references('id')
                ->on('chat_tickets');
            // FK message_id -> chat_messages adicionada em migration posterior (cross-domain)
            $table->index(['tenant_id', 'status'], 'idx_ai_post_sale_schedules_tenant_status');
            $table->index(['negotiation_id'], 'idx_ai_post_sale_schedules_negotiation_id');
            $table->index(['ticket_id'], 'idx_ai_post_sale_schedules_ticket_id');
            $table->index(['scheduled_at'], 'idx_ai_post_sale_schedules_scheduled_at');
            $table->index(['message_id'], 'idx_ai_post_sale_schedules_message_id');
        });
    }

    private function createAgents(): void
    {
        Schema::create('ai_agents', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do agente (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono do agente');
            $table->string('name', 100)->comment('Nome do agente');
            $table->string('type', 30)->default('general')->comment('Tipo (assistant/classifier/general)');
            $table->string('model_id', 50)->default('gpt-4o-mini')->comment('ID do modelo de IA');
            $table->text('system_prompt')->nullable()->comment('Prompt de sistema');
            $table->integer('max_tokens')->default(2048)->comment('Máximo de tokens por resposta');
            $table->decimal('temperature', 3, 2)->default(0.7)->comment('Temperatura de geração (0-2)');
            $table->decimal('top_p', 3, 2)->default(1)->comment('Top P sampling');
            $table->boolean('is_active')->default(true)->comment('Indica se o agente está ativo');
            $table->timestamps(0);
            $table->uuid('parent_agent_id')->nullable()->comment('Agente pai (hierarquia)');
            $table->string('classifier_model', 50)->nullable()->comment('Modelo classificador');
            $table->integer('token_budget_input')->default(0)->comment('Orçamento de tokens de entrada');
            $table->integer('token_budget_output')->default(0)->comment('Orçamento de tokens de saída');
            $table->text('fallback_message')->nullable()->comment('Mensagem quando não consegue responder');
            $table->json('metadata')->nullable()->comment('Metadados adicionais');
            $table->string('voice_response_mode', 20)->default('text')->comment('Modo de resposta por voz');
            $table->string('stt_model', 50)->nullable()->comment('Modelo Speech-to-Text');
            $table->string('stt_language', 16)->nullable()->comment('Idioma STT');
            $table->string('tts_model', 50)->nullable()->comment('Modelo Text-to-Speech');
            $table->string('tts_voice', 50)->nullable()->comment('Voz TTS');
            $table->decimal('tts_speed', 4, 2)->default(1)->comment('Velocidade da fala');
            $table->text('description')->nullable()->comment('Descrição do agente');

            $table->foreign('tenant_id', 'fk_ai_agents_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            // Self-reference FK parent_agent_id adicionada em migration posterior
            $table->index(['tenant_id', 'is_active'], 'idx_ai_agents_tenant_active');
            $table->index(['type'], 'idx_ai_agents_type');
            $table->index(['parent_agent_id'], 'idx_ai_agents_parent_agent_id');
        });
    }

    private function createAgentSkills(): void
    {
        Schema::create('ai_agent_skills', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único da skill (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono da skill');
            $table->uuid('agent_id')->comment('Agente vinculado');
            $table->string('name', 100)->comment('Nome da skill');
            $table->text('description')->nullable()->comment('Descrição da skill');
            $table->boolean('is_active')->default(true)->comment('Indica se a skill está ativa');
            $table->jsonb('metadata')->nullable()->comment('Metadados adicionais');
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_ai_agent_skills_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('agent_id', 'fk_ai_agent_skills_agent_id')
                ->references('id')
                ->on('ai_agents');
            $table->index(['tenant_id', 'agent_id'], 'idx_ai_agent_skills_tenant_agent');
            $table->index(['agent_id', 'is_active'], 'idx_ai_agent_skills_agent_active');
        });

        DB::statement("ALTER TABLE ai_agent_skills ALTER COLUMN metadata TYPE json");
    }

    private function createAgentDelegations(): void
    {
        Schema::create('ai_agent_delegations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único da delegação (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono da regra');
            $table->uuid('source_agent_id')->comment('Agente que delega');
            $table->uuid('target_agent_id')->comment('Agente que recebe a delegação');
                $table->smallInteger('max_depth')->default(1)->comment('Profundidade máxima de delegação');
            $table->boolean('is_active')->default(true)->comment('Indica se a delegação está ativa');
            $table->jsonb('metadata')->nullable()->comment('Metadados adicionais');
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_ai_agent_delegations_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('source_agent_id', 'fk_ai_agent_delegations_source_agent_id')
                ->references('id')
                ->on('ai_agents');
            $table->foreign('target_agent_id', 'fk_ai_agent_delegations_target_agent_id')
                ->references('id')
                ->on('ai_agents');
            $table->index(['tenant_id', 'is_active'], 'idx_ai_agent_delegations_tenant_active');
            $table->index(['source_agent_id'], 'idx_ai_agent_delegations_source_agent');
            $table->index(['target_agent_id'], 'idx_ai_agent_delegations_target_agent');
        });

        DB::statement("ALTER TABLE ai_agent_delegations ALTER COLUMN metadata TYPE json");
    }

    private function createAgentFiles(): void
    {
        Schema::create('ai_agent_files', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do arquivo (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono do arquivo');
            $table->uuid('agent_id')->comment('Agente vinculado');
            $table->string('slug', 100)->comment('Identificador único do arquivo no agente');
            $table->text('content')->nullable()->comment('Conteúdo do arquivo (markdown, JSON, etc.)');
            $table->uuid('updated_by')->nullable()->comment('Usuário que atualizou por último');
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_ai_agent_files_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('agent_id', 'fk_ai_agent_files_agent_id')
                ->references('id')
                ->on('ai_agents');
            $table->foreign('updated_by', 'fk_ai_agent_files_updated_by')
                ->references('id')
                ->on('auth_users');
            $table->index(['tenant_id', 'agent_id'], 'idx_ai_agent_files_tenant_agent');
            $table->index(['agent_id', 'slug'], 'idx_ai_agent_files_agent_slug');
            $table->unique(['agent_id', 'slug'], 'uniq_ai_agent_files_agent_slug');
        });
    }

    private function createAgentTools(): void
    {
        Schema::create('ai_agent_tools', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do registro (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono do registro');
            $table->uuid('agent_id')->comment('Agente vinculado');
            $table->uuid('tool_id')->comment('Ferramenta vinculada');
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_ai_agent_tools_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('agent_id', 'fk_ai_agent_tools_agent_id')
                ->references('id')
                ->on('ai_agents');
            $table->foreign('tool_id', 'fk_ai_agent_tools_tool_id')
                ->references('id')
                ->on('ai_autopilot_tools');
            $table->index(['tenant_id', 'agent_id'], 'idx_ai_agent_tools_tenant_agent');
            $table->index(['agent_id', 'tool_id'], 'idx_ai_agent_tools_agent_tool');
            $table->unique(['agent_id', 'tool_id'], 'uniq_ai_agent_tools_agent_tool');
        });
    }

    private function createAgentTriggers(): void
    {
        Schema::create('ai_agent_triggers', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do gatilho (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono do gatilho');
            $table->uuid('agent_id')->comment('Agente vinculado');
            $table->string('type', 50)->comment('Tipo de gatilho (keyword/schedule/webhook)');
                $table->jsonb('config')->nullable()->comment('Configuração do gatilho em JSON');
            $table->string('status', 20)->default('active')->comment('Status do gatilho');
            $table->timestamp('last_run_at')->nullable()->comment('Última execução do gatilho');
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_ai_agent_triggers_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('agent_id', 'fk_ai_agent_triggers_agent_id')
                ->references('id')
                ->on('ai_agents');
            $table->index(['tenant_id', 'status'], 'idx_ai_agent_triggers_tenant_status');
            $table->index(['agent_id', 'type'], 'idx_ai_agent_triggers_agent_type');
            $table->index(['last_run_at'], 'idx_ai_agent_triggers_last_run_at');
        });

        DB::statement("ALTER TABLE ai_agent_triggers ALTER COLUMN config TYPE json");
    }

    private function createConversationSummaries(): void
    {
        Schema::create('ai_conversation_summaries', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do resumo (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono do resumo');
            $table->uuid('ticket_id')->comment('Ticket de chat resumido');
            $table->text('summary')->comment('Resumo da conversa');
            $table->integer('message_count')->default(0)->comment('Quantidade de mensagens resumidas');
            $table->timestamp('generated_at')->nullable()->comment('Quando o resumo foi gerado');
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_ai_conversation_summaries_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('ticket_id', 'fk_ai_conversation_summaries_ticket_id')
                ->references('id')
                ->on('chat_tickets');
            $table->index(['tenant_id', 'ticket_id'], 'idx_ai_conversation_summaries_tenant_ticket');
            $table->index(['ticket_id'], 'idx_ai_conversation_summaries_ticket_id');
            $table->index(['generated_at'], 'idx_ai_conversation_summaries_generated_at');
        });
    }

    private function createAgentChannels(): void
    {
        Schema::create('ai_agent_channels', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do canal (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono do canal');
            $table->uuid('agent_id')->comment('Agente vinculado');
            $table->string('channel', 20)->comment('Canal (whatsapp/telegram/web)');
            $table->string('external_ref', 255)->nullable()->comment('Referência externa do canal');
            $table->boolean('is_active')->default(true)->comment('Indica se o canal está ativo');
            $table->jsonb('metadata')->nullable()->comment('Metadados adicionais');
            $table->timestamps();

            $table->foreign('tenant_id', 'fk_ai_agent_channels_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('agent_id', 'fk_ai_agent_channels_agent_id')
                ->references('id')
                ->on('ai_agents');
            $table->index(['tenant_id', 'agent_id'], 'idx_ai_agent_channels_tenant_agent');
            $table->index(['agent_id', 'channel'], 'idx_ai_agent_channels_agent_channel');
            $table->index(['channel', 'is_active'], 'idx_ai_agent_channels_channel_active');
        });

        DB::statement("ALTER TABLE ai_agent_channels ALTER COLUMN metadata TYPE json");
    }
};
