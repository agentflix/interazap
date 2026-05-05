<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas centrais do contexto Chat para gerenciamento de
 * instâncias de mensageria, tickets de atendimento, mensagens e
 * sessões de conversação.
 *
 * Tabelas criadas:
 * - chat_instances: Instâncias de provedores (WhatsApp/Meta/Telegram)
 * - chat_ticket_sequences: Sequenciador de protocolos por tenant
 * - chat_tickets: Tickets de atendimento ao cliente
 * - chat_tickets_extended: Particionamento vertical de tickets (baixa frequência)
 * - chat_messages: Mensagens trocadas nos tickets
 * - chat_messages_extended: Particionamento vertical de mensagens
 * - chat_message_templates: Templates de mensagens (ex: Meta HSM)
 * - chat_ticket_transfers: Transferências de tickets entre atendentes
 * - chat_message_interactions: Interações com botões/listas em mensagens
 * - chat_sessions: Sessões de chat web/tokenizadas
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_instances')) {
            Schema::create('chat_instances', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da instância');
                $table->uuid('tenant_id')->comment('Tenant proprietário da instância');
                $table->string('provider', 50)->comment('Provedor da instância (whatsapp/meta/telegram)');
                $table->string('name', 255)->nullable()->comment('Nome descritivo da instância');
                $table->string('mode', 20)->default('production')->comment('Modo de operação (single/multi)');
                $table->string('status', 50)->default('disconnected')->comment('Status atual da instância');
                $table->boolean('is_active')->default(true)->comment('Se a instância está ativa');
                $table->boolean('evaluation_enabled')->default(false)->comment('Se avaliação de atendimento está habilitada');
                $table->smallInteger('evaluation_cutoff_score')->default(3)->comment('Nota mínima de corte para avaliação');
                $table->string('webhook_token', 255)->comment('Token para validação de webhooks recebidos');
                $table->json('settings_json')->nullable()->comment('Configurações diversas em JSON');
                $table->boolean('auto_close_enabled')->nullable()->comment('Se auto-fechamento de tickets está ativo');
                $table->smallInteger('auto_close_after_minutes')->nullable()->comment('Minutos para auto-fechar tickets inativos');
                $table->string('auto_close_target', 10)->nullable()->comment('Alvo do auto-fechamento (resolved/none)');
                $table->text('auto_close_message')->nullable()->comment('Mensagem enviada ao auto-fechar');
                $table->timestamp('last_status_at', 0)->nullable()->comment('Última atualização de status');
                $table->timestamps(0);

                $table->index('tenant_id', 'idx_chat_instances_tenant_id');
                $table->index('provider', 'idx_chat_instances_provider');
                $table->index('status', 'idx_chat_instances_status');
                $table->index('is_active', 'idx_chat_instances_is_active');
                $table->foreign('tenant_id', 'fk_chat_instances_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('chat_ticket_sequences')) {
            Schema::create('chat_ticket_sequences', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do sequenciador');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->bigInteger('current_value')->default(0)->comment('Último número de protocolo utilizado');
                $table->string('prefix', 10)->default('')->comment('Prefixo do protocolo (ex: SUP-)');
                $table->timestamps(0);

                $table->index('tenant_id', 'idx_chat_ticket_sequences_tenant_id');
                $table->foreign('tenant_id', 'fk_chat_ticket_sequences_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('chat_tickets')) {
            Schema::create('chat_tickets', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do ticket');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('contact_id')->nullable()->comment('ID do contato (referência externa ou CRM)');
                $table->uuid('instance_id')->nullable()->comment('Instância de chat vinculada');
                $table->uuid('assigned_to')->nullable()->comment('Atendente responsável');
                $table->uuid('current_ai_agent_id')->nullable()->comment('Agente IA atual (sticky session)');
                $table->string('protocol', 50)->nullable()->comment('Número de protocolo do atendimento');
                $table->string('channel', 20)->default('whatsapp')->comment('Canal de origem (whatsapp/meta/telegram/web)');
                $table->string('remote_jid', 100)->nullable()->comment('JID remoto do WhatsApp');
                $table->string('phone', 20)->nullable()->comment('Telefone formatado');
                $table->string('phone_e164', 20)->nullable()->comment('Telefone no formato E.164');
                $table->string('push_name', 255)->nullable()->comment('Nome exibido no WhatsApp');
                $table->string('status', 20)->default('pending')->comment('Status do ticket (open/pending/closed)');
                $table->string('priority', 20)->default('normal')->comment('Prioridade (low/normal/high/urgent)');
                $table->string('category', 50)->nullable()->comment('Categoria ou classificação do ticket');
                $table->boolean('is_group')->default(false)->comment('Se a conversa é um grupo');
                $table->boolean('is_bot_active')->default(true)->comment('Se o bot está ativo no ticket');
                $table->timestamp('started_at')->nullable()->comment('Quando o ticket foi iniciado');
                $table->timestamp('first_response_at')->nullable()->comment('Primeira resposta do atendente');
                $table->timestamp('last_message_at')->nullable()->comment('Data da última mensagem');
                $table->timestamp('last_customer_message_at')->nullable()->comment('Data da última mensagem do cliente');
                $table->timestamp('last_agent_message_at')->nullable()->comment('Data da última mensagem do atendente');
                $table->timestamp('closed_at')->nullable()->comment('Quando o ticket foi fechado');
                $table->string('closed_mode', 20)->nullable()->comment('Modo de fechamento (manual/auto)');
                $table->string('sentiment', 20)->nullable()->comment('Sentimento do ticket (positive/neutral/negative)');
                $table->smallInteger('sentiment_score')->nullable()->comment('Score de sentimento (escala inteira)');
                $table->timestamp('sentiment_updated_at')->nullable()->comment('Última atualização do sentimento');
                $table->jsonb('tags')->nullable()->comment('Tags aplicadas ao ticket');
                $table->jsonb('metadata')->nullable()->comment('Metadados diversos');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id', 'idx_chat_tickets_tenant_id');
                $table->index('contact_id', 'idx_chat_tickets_contact_id');
                $table->index('instance_id', 'idx_chat_tickets_instance_id');
                $table->index('assigned_to', 'idx_chat_tickets_assigned_to');
                $table->index('current_ai_agent_id', 'idx_chat_tickets_current_ai_agent_id');
                $table->index('protocol', 'idx_chat_tickets_protocol');
                $table->index('status', 'idx_chat_tickets_status');
                $table->index('priority', 'idx_chat_tickets_priority');
                $table->index('closed_at', 'idx_chat_tickets_closed_at');
                $table->index('last_message_at', 'idx_chat_tickets_last_message_at');
                $table->index('sentiment', 'idx_chat_tickets_sentiment');

                $table->foreign('tenant_id', 'fk_chat_tickets_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('instance_id', 'fk_chat_tickets_instance_id')
                    ->references('id')
                    ->on('chat_instances')
                    ->onDelete('cascade');
                $table->foreign('assigned_to', 'fk_chat_tickets_assigned_to')
                    ->references('id')
                    ->on('auth_users')
                    ->onDelete('set null');
                // FK current_ai_agent_id -> ai_agents adicionada em migration posterior (cross-domain)
            });
        }

        if (! Schema::hasTable('chat_tickets_extended')) {
            Schema::create('chat_tickets_extended', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da extensão');
                $table->uuid('ticket_id')->comment('Ticket vinculado');
                $table->text('subject')->nullable()->comment('Assunto detalhado do ticket');
                $table->string('profile_picture_url', 500)->nullable()->comment('URL da foto de perfil');
                $table->timestamp('human_takeover_at')->nullable()->comment('Quando humano assumiu');
                $table->uuid('closed_by')->nullable()->comment('Quem fechou o ticket');
                $table->string('close_reason', 50)->nullable()->comment('Motivo do fechamento');
                $table->integer('auto_close_queue_after_minutes')->default(0)->comment('Minutos para auto-fechar em fila');
                $table->integer('auto_close_in_progress_after_minutes')->default(0)->comment('Minutos para auto-fechar em andamento');
                $table->timestamp('sla_first_response_due_at')->nullable()->comment('Prazo SLA primeira resposta');
                $table->timestamp('sla_resolution_due_at')->nullable()->comment('Prazo SLA resolução');
                $table->boolean('sla_first_response_breached')->default(false)->comment('SLA primeira resposta estourado');
                $table->boolean('sla_resolution_breached')->default(false)->comment('SLA resolução estourado');
                $table->timestamps();

                $table->index('ticket_id', 'idx_chat_tickets_extended_ticket_id');
                $table->foreign('ticket_id', 'fk_chat_tickets_extended_ticket_id')
                    ->references('id')
                    ->on('chat_tickets')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da mensagem');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('ticket_id')->comment('Ticket vinculado');
                $table->uuid('user_id')->nullable()->comment('Atendente que enviou a mensagem');
                $table->uuid('contact_id')->nullable()->comment('Contato que enviou a mensagem');
                $table->text('content')->nullable()->comment('Conteúdo textual da mensagem');
                $table->string('type', 30)->default('text')->comment('Tipo da mensagem (text/image/audio/video/document/location/template/button/interactive)');
                $table->string('direction', 10)->default('incoming')->comment('Direção (inbound/outbound)');
                $table->boolean('is_from_contact')->default(false)->comment('Se a mensagem veio do contato');
                $table->string('source', 30)->default('webhook')->comment('Origem da mensagem (webhook/api/manual/bot)');
                $table->string('status', 20)->default('pending')->comment('Status de entrega (pending/sent/delivered/read/failed)');
                $table->string('external_id', 255)->nullable()->comment('ID externo no provedor');
                $table->jsonb('metadata')->nullable()->comment('Metadados da mensagem');
                $table->timestamp('sent_at')->nullable()->comment('Quando foi enviada');
                $table->timestamp('delivered_at')->nullable()->comment('Quando foi entregue');
                $table->timestamp('read_at')->nullable()->comment('Quando foi lida');
                $table->boolean('is_deleted')->default(false)->comment('Se a mensagem foi deletada');
                $table->timestamp('deleted_at')->nullable()->comment('Data do soft delete da mensagem');
                $table->uuid('deleted_by')->nullable()->comment('Usuário que deletou');
                $table->text('transcription')->nullable()->comment('Transcrição de áudio');
                $table->integer('audio_duration_ms')->nullable()->comment('Duração do áudio em milissegundos');
                $table->string('audio_mime_type', 50)->nullable()->comment('MIME type do áudio');
                $table->timestamps();

                $table->index('tenant_id', 'idx_chat_messages_tenant_id');
                $table->index('ticket_id', 'idx_chat_messages_ticket_id');
                $table->index('user_id', 'idx_chat_messages_user_id');
                $table->index('contact_id', 'idx_chat_messages_contact_id');
                $table->index('type', 'idx_chat_messages_type');
                $table->index('direction', 'idx_chat_messages_direction');
                $table->index('status', 'idx_chat_messages_status');
                $table->index('external_id', 'idx_chat_messages_external_id');
                $table->index('sent_at', 'idx_chat_messages_sent_at');
                $table->index('is_deleted', 'idx_chat_messages_is_deleted');

                $table->foreign('tenant_id', 'fk_chat_messages_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('ticket_id', 'fk_chat_messages_ticket_id')
                    ->references('id')
                    ->on('chat_tickets')
                    ->onDelete('cascade');
                $table->foreign('user_id', 'fk_chat_messages_user_id')
                    ->references('id')
                    ->on('auth_users')
                    ->onDelete('set null');
            });
        }

        if (! Schema::hasTable('chat_messages_extended')) {
            Schema::create('chat_messages_extended', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da extensão');
                $table->uuid('message_id')->comment('Mensagem vinculada');
                $table->string('file_url', 500)->nullable()->comment('URL do arquivo anexado');
                $table->string('file_name', 255)->nullable()->comment('Nome do arquivo');
                $table->string('mime_type', 100)->nullable()->comment('Tipo MIME do arquivo');
                $table->bigInteger('file_size')->nullable()->comment('Tamanho do arquivo em bytes');
                $table->string('media_transcription_status', 20)->nullable()->comment('Status da transcrição de mídia');
                $table->string('media_transcription_provider', 50)->nullable()->comment('Provedor da transcrição');
                $table->timestamp('media_transcribed_at', 0)->nullable()->comment('Quando a transcrição foi feita');
                $table->text('media_transcription')->nullable()->comment('Texto da transcrição');
                $table->decimal('media_transcription_cost', 10, 6)->nullable()->comment('Custo da transcrição');
                $table->integer('media_transcription_tokens')->nullable()->comment('Tokens usados na transcrição');
                $table->jsonb('reactions')->nullable()->comment('Reações à mensagem');
                $table->boolean('is_edited')->default(false)->comment('Se a mensagem foi editada');
                $table->timestamp('edited_at')->nullable()->comment('Quando foi editada');
                $table->jsonb('edit_history')->nullable()->comment('Histórico de edições');
                $table->string('error_message', 255)->nullable()->comment('Mensagem de erro de envio');
                $table->timestamps();

                $table->index('message_id', 'idx_chat_messages_extended_message_id');
                $table->foreign('message_id', 'fk_chat_messages_extended_message_id')
                    ->references('id')
                    ->on('chat_messages')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('chat_message_templates')) {
            Schema::create('chat_message_templates', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do template');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('chat_instance_id')->nullable()->comment('Instância Meta vinculada');
                $table->string('name', 255)->comment('Nome do template');
                $table->string('shortcut', 50)->nullable()->comment('Atalho para uso rápido');
                $table->text('content')->comment('Conteúdo do template');
                $table->string('provider', 20)->default('local')->comment('Provedor (meta/whatsapp)');
                $table->string('external_id', 255)->nullable()->comment('ID externo no Meta');
                $table->string('language', 10)->default('pt_BR')->comment('Idioma do template');
                $table->string('category', 50)->nullable()->comment('Categoria do template');
                $table->string('status', 20)->default('approved')->comment('Status no Meta (approved/pending/rejected)');
                $table->text('rejected_reason')->nullable()->comment('Motivo da rejeição no Meta');
                $table->jsonb('components_json')->nullable()->comment('Componentes do template Meta em JSON');
                $table->timestamp('last_synced_at', 0)->nullable()->comment('Última sincronização com Meta');
                $table->boolean('is_active')->default(true)->comment('Se o template está ativo');
                $table->timestamps(0);
                $table->softDeletes();

                $table->index('tenant_id', 'idx_chat_message_templates_tenant_id');
                $table->index('chat_instance_id', 'idx_chat_message_templates_chat_instance_id');
                $table->index('external_id', 'idx_chat_message_templates_external_id');
                $table->index('status', 'idx_chat_message_templates_status');
                $table->index('is_active', 'idx_chat_message_templates_is_active');

                $table->foreign('tenant_id', 'fk_chat_message_templates_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('chat_instance_id', 'fk_chat_message_templates_chat_instance_id')
                    ->references('id')
                    ->on('chat_instances')
                    ->onDelete('set null');
            });
        }

        if (! Schema::hasTable('chat_ticket_transfers')) {
            Schema::create('chat_ticket_transfers', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da transferência');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('ticket_id')->comment('Ticket transferido');
                $table->uuid('from_user_id')->nullable()->comment('Atendente de origem');
                $table->uuid('to_user_id')->nullable()->comment('Atendente de destino');
                $table->text('reason')->nullable()->comment('Motivo da transferência');
                $table->string('status', 20)->default('pending')->comment('Status da transferência (pending/accepted/rejected)');
                $table->timestamp('transferred_at')->nullable()->comment('Quando foi transferido');
                $table->timestamps();

                $table->index('tenant_id', 'idx_chat_ticket_transfers_tenant_id');
                $table->index('ticket_id', 'idx_chat_ticket_transfers_ticket_id');
                $table->index('from_user_id', 'idx_chat_ticket_transfers_from_user_id');
                $table->index('to_user_id', 'idx_chat_ticket_transfers_to_user_id');
                $table->index('status', 'idx_chat_ticket_transfers_status');

                $table->foreign('tenant_id', 'fk_chat_ticket_transfers_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('ticket_id', 'fk_chat_ticket_transfers_ticket_id')
                    ->references('id')
                    ->on('chat_tickets')
                    ->onDelete('cascade');
                $table->foreign('from_user_id', 'fk_chat_ticket_transfers_from_user_id')
                    ->references('id')
                    ->on('auth_users')
                    ->onDelete('set null');
                $table->foreign('to_user_id', 'fk_chat_ticket_transfers_to_user_id')
                    ->references('id')
                    ->on('auth_users')
                    ->onDelete('set null');
            });
        }

        if (! Schema::hasTable('chat_message_interactions')) {
            Schema::create('chat_message_interactions', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da interação');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('message_id')->comment('Mensagem que recebeu a interação');
                $table->string('interaction_type', 50)->comment('Tipo de interação (button_reply/list_reply/quick_reply)');
                $table->uuid('user_id')->nullable()->comment('Usuário que interagiu');
                $table->json('data')->nullable()->comment('Dados da interação em JSON');
                $table->timestamps();

                $table->index('tenant_id', 'idx_chat_message_interactions_tenant_id');
                $table->index('message_id', 'idx_chat_message_interactions_message_id');
                $table->index('interaction_type', 'idx_chat_message_interactions_interaction_type');
                $table->index('user_id', 'idx_chat_message_interactions_user_id');

                $table->foreign('tenant_id', 'fk_chat_message_interactions_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('message_id', 'fk_chat_message_interactions_message_id')
                    ->references('id')
                    ->on('chat_messages')
                    ->onDelete('cascade');
                $table->foreign('user_id', 'fk_chat_message_interactions_user_id')
                    ->references('id')
                    ->on('auth_users')
                    ->onDelete('set null');
            });

            DB::statement("ALTER TABLE chat_message_interactions ALTER COLUMN data TYPE json");
        }

        if (! Schema::hasTable('chat_sessions')) {
            Schema::create('chat_sessions', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da sessão');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('contact_id')->nullable()->comment('ID do contato vinculado');
                $table->uuid('ticket_id')->comment('Ticket vinculado');
                $table->string('token', 255)->unique()->comment('Token único da sessão');
                $table->json('client_info')->nullable()->comment('Informações do cliente (IP, User-Agent)');
                $table->timestamp('last_activity_at')->nullable()->comment('Última atividade na sessão');
                $table->timestamps();

                $table->index('tenant_id', 'idx_chat_sessions_tenant_id');
                $table->index('contact_id', 'idx_chat_sessions_contact_id');
                $table->index('ticket_id', 'idx_chat_sessions_ticket_id');
                $table->index('token', 'idx_chat_sessions_token');

                $table->foreign('tenant_id', 'fk_chat_sessions_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('ticket_id', 'fk_chat_sessions_ticket_id')
                    ->references('id')
                    ->on('chat_tickets')
                    ->onDelete('set null');
            });

            DB::statement("ALTER TABLE chat_sessions ALTER COLUMN client_info TYPE json");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
        Schema::dropIfExists('chat_message_interactions');
        Schema::dropIfExists('chat_ticket_transfers');
        Schema::dropIfExists('chat_message_templates');
        Schema::dropIfExists('chat_messages_extended');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_tickets_extended');
        Schema::dropIfExists('chat_tickets');
        Schema::dropIfExists('chat_ticket_sequences');
        Schema::dropIfExists('chat_instances');
    }
};
