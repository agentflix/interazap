<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas de recursos avançados do contexto Chat para automação,
 * transmissões em massa, respostas rápidas, avaliações e roteamento
 * inteligente de tickets.
 *
 * Tabelas criadas:
 * - chat_auto_reply_rules: Regras de resposta automática e boas-vindas
 * - chat_auto_reply_cooldowns: Controle de cooldown entre respostas automáticas
 * - chat_transmission_lists: Listas de transmissão em massa
 * - chat_transmission_list_contacts: Contatos vinculados às listas de transmissão
 * - chat_quick_answers: Respostas rápidas para atendentes
 * - chat_ticket_evaluations: Avaliações de satisfação do cliente
 * - chat_routing_queues: Filas de roteamento de tickets
 * - chat_routing_queue_agents: Agentes vinculados às filas de roteamento
 * - chat_routing_agent_skills: Skills dos agentes para roteamento baseado em habilidade
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_auto_reply_rules')) {
            Schema::create('chat_auto_reply_rules', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da regra');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->string('name', 255)->comment('Nome descritivo da regra');
                $table->string('trigger_text', 255)->comment('Texto ou padrão que dispara a regra');
                $table->text('response_text')->comment('Texto de resposta automática');
                $table->boolean('is_active')->default(true)->comment('Se a regra está ativa');
                $table->boolean('is_welcome')->default(false)->comment('Se é mensagem de boas-vindas');
                $table->integer('cooldown_seconds')->default(0)->comment('Tempo de cooldown em segundos');
                $table->timestamps(0);

                $table->index('tenant_id', 'idx_chat_auto_reply_rules_tenant_id');
                $table->index('is_active', 'idx_chat_auto_reply_rules_is_active');
                $table->index('is_welcome', 'idx_chat_auto_reply_rules_is_welcome');

                $table->foreign('tenant_id', 'fk_chat_auto_reply_rules_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('chat_auto_reply_cooldowns')) {
            Schema::create('chat_auto_reply_cooldowns', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do cooldown');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('ticket_id')->comment('Ticket em cooldown');
                $table->uuid('rule_id')->comment('Regra que gerou o cooldown');
                $table->timestamp('cooldown_until')->nullable()->comment('Até quando o cooldown está ativo');
                $table->timestamps();

                $table->index('tenant_id', 'idx_chat_auto_reply_cooldowns_tenant_id');
                $table->index('ticket_id', 'idx_chat_auto_reply_cooldowns_ticket_id');
                $table->index('rule_id', 'idx_chat_auto_reply_cooldowns_rule_id');
                $table->index('cooldown_until', 'idx_chat_auto_reply_cooldowns_cooldown_until');

                $table->foreign('tenant_id', 'fk_chat_auto_reply_cooldowns_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('ticket_id', 'fk_chat_auto_reply_cooldowns_ticket_id')
                    ->references('id')
                    ->on('chat_tickets')
                    ->onDelete('cascade');
                $table->foreign('rule_id', 'fk_chat_auto_reply_cooldowns_rule_id')
                    ->references('id')
                    ->on('chat_auto_reply_rules')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('chat_transmission_lists')) {
            Schema::create('chat_transmission_lists', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da lista');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('instance_id')->nullable()->comment('Instância de chat vinculada');
                $table->string('name', 255)->comment('Nome da lista de transmissão');
                $table->text('message')->nullable()->comment('Mensagem a ser enviada');
                $table->jsonb('filter_criteria')->nullable()->comment('Critérios de filtro dos contatos');
                $table->string('status', 20)->default('draft')->comment('Status (draft/scheduled/sending/sent/cancelled)');
                $table->timestamp('scheduled_at')->nullable()->comment('Quando agendado para envio');
                $table->json('metadata')->nullable()->comment('Metadados diversos');
                $table->timestamps();

                $table->index('tenant_id', 'idx_chat_transmission_lists_tenant_id');
                $table->index('instance_id', 'idx_chat_transmission_lists_instance_id');
                $table->index('status', 'idx_chat_transmission_lists_status');
                $table->index('scheduled_at', 'idx_chat_transmission_lists_scheduled_at');

                $table->foreign('tenant_id', 'fk_chat_transmission_lists_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('instance_id', 'fk_chat_transmission_lists_instance_id')
                    ->references('id')
                    ->on('chat_instances')
                    ->onDelete('set null');
            });

            DB::statement('ALTER TABLE chat_transmission_lists ALTER COLUMN metadata TYPE json');
        }

        if (! Schema::hasTable('chat_transmission_list_contacts')) {
            Schema::create('chat_transmission_list_contacts', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do registro');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('transmission_list_id')->comment('Lista de transmissão vinculada');
                $table->uuid('contact_id')->comment('ID do contato destinatário');
                $table->string('status', 20)->default('pending')->comment('Status de envio (pending/sent/failed)');
                $table->timestamp('sent_at')->nullable()->comment('Quando foi enviado');
                $table->text('error')->nullable()->comment('Erro no envio');
                $table->timestamps();

                $table->index('tenant_id', 'idx_chat_transmission_list_contacts_tenant_id');
                $table->index('transmission_list_id', 'idx_chat_transmission_list_contacts_list_id');
                $table->index('contact_id', 'idx_chat_transmission_list_contacts_contact_id');
                $table->index('status', 'idx_chat_transmission_list_contacts_status');

                $table->foreign('tenant_id', 'fk_chat_transmission_list_contacts_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('transmission_list_id', 'fk_chat_transmission_list_contacts_list_id')
                    ->references('id')
                    ->on('chat_transmission_lists')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('chat_quick_answers')) {
            Schema::create('chat_quick_answers', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da resposta rápida');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->string('name', 255)->comment('Nome da resposta rápida');
                $table->string('shortcut', 50)->nullable()->comment('Atalho para uso rápido (ex: /nome)');
                $table->text('content')->comment('Conteúdo da resposta');
                $table->string('category', 50)->nullable()->comment('Categoria da resposta');
                $table->boolean('is_active')->default(true)->comment('Se a resposta está ativa');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id', 'idx_chat_quick_answers_tenant_id');
                $table->index('shortcut', 'idx_chat_quick_answers_shortcut');
                $table->index('category', 'idx_chat_quick_answers_category');
                $table->index('is_active', 'idx_chat_quick_answers_is_active');

                $table->foreign('tenant_id', 'fk_chat_quick_answers_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('chat_ticket_evaluations')) {
            Schema::create('chat_ticket_evaluations', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da avaliação');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('ticket_id')->comment('Ticket avaliado');
                $table->string('token', 255)->unique()->comment('Token único para acesso à avaliação');
                $table->smallInteger('rating')->default(0)->comment('Nota de 1 a 5');
                $table->text('comment')->nullable()->comment('Comentário do cliente');
                $table->timestamp('submitted_at', 0)->nullable()->comment('Quando foi enviada');
                $table->timestamps(0);

                $table->index('tenant_id', 'idx_chat_ticket_evaluations_tenant_id');
                $table->index('ticket_id', 'idx_chat_ticket_evaluations_ticket_id');
                $table->index('token', 'idx_chat_ticket_evaluations_token');
                $table->index('rating', 'idx_chat_ticket_evaluations_rating');

                $table->foreign('tenant_id', 'fk_chat_ticket_evaluations_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('ticket_id', 'fk_chat_ticket_evaluations_ticket_id')
                    ->references('id')
                    ->on('chat_tickets')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('chat_routing_queues')) {
            Schema::create('chat_routing_queues', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da fila');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('instance_id')->nullable()->comment('Instância de chat vinculada');
                $table->string('name', 255)->comment('Nome da fila de roteamento');
                $table->boolean('is_enabled')->default(false)->comment('Se a fila está habilitada');
                $table->string('strategy', 20)->default('round_robin')->comment('Estratégia de roteamento (round_robin/least_busy/skill_based)');
                $table->integer('max_open_tickets_per_agent')->nullable()->comment('Máximo de tickets abertos por agente');
                $table->timestamps();

                $table->index('tenant_id', 'idx_chat_routing_queues_tenant_id');
                $table->index('instance_id', 'idx_chat_routing_queues_instance_id');
                $table->index('is_enabled', 'idx_chat_routing_queues_is_enabled');
                $table->index('strategy', 'idx_chat_routing_queues_strategy');

                $table->foreign('tenant_id', 'fk_chat_routing_queues_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('instance_id', 'fk_chat_routing_queues_instance_id')
                    ->references('id')
                    ->on('chat_instances')
                    ->onDelete('set null');
            });
        }

        if (! Schema::hasTable('chat_routing_queue_agents')) {
            Schema::create('chat_routing_queue_agents', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do vínculo');
                $table->uuid('queue_id')->comment('Fila de roteamento');
                $table->uuid('user_id')->comment('Agente/atendente');
                $table->integer('position')->default(0)->comment('Ordem na fila');
                $table->timestamp('last_assigned_at')->nullable()->comment('Último ticket atribuído ao agente');
                $table->boolean('is_active')->default(true)->comment('Se o agente está ativo na fila');
                $table->timestamps();

                $table->index('queue_id', 'idx_chat_routing_queue_agents_queue_id');
                $table->index('user_id', 'idx_chat_routing_queue_agents_user_id');
                $table->index('is_active', 'idx_chat_routing_queue_agents_is_active');

                $table->foreign('queue_id', 'fk_chat_routing_queue_agents_queue_id')
                    ->references('id')
                    ->on('chat_routing_queues')
                    ->onDelete('cascade');
                $table->foreign('user_id', 'fk_chat_routing_queue_agents_user_id')
                    ->references('id')
                    ->on('auth_users')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('chat_routing_agent_skills')) {
            Schema::create('chat_routing_agent_skills', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da skill');
                $table->uuid('queue_id')->comment('Fila de roteamento');
                $table->uuid('user_id')->comment('Agente/atendente');
                $table->string('skill', 100)->comment('Nome da skill (ex: vendas, suporte_tecnico)');
                $table->timestamps();

                $table->index('queue_id', 'idx_chat_routing_agent_skills_queue_id');
                $table->index('user_id', 'idx_chat_routing_agent_skills_user_id');
                $table->index('skill', 'idx_chat_routing_agent_skills_skill');

                $table->foreign('queue_id', 'fk_chat_routing_agent_skills_queue_id')
                    ->references('id')
                    ->on('chat_routing_queues')
                    ->onDelete('cascade');
                $table->foreign('user_id', 'fk_chat_routing_agent_skills_user_id')
                    ->references('id')
                    ->on('auth_users')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_routing_agent_skills');
        Schema::dropIfExists('chat_routing_queue_agents');
        Schema::dropIfExists('chat_routing_queues');
        Schema::dropIfExists('chat_ticket_evaluations');
        Schema::dropIfExists('chat_quick_answers');
        Schema::dropIfExists('chat_transmission_list_contacts');
        Schema::dropIfExists('chat_transmission_lists');
        Schema::dropIfExists('chat_auto_reply_cooldowns');
        Schema::dropIfExists('chat_auto_reply_rules');
    }
};
