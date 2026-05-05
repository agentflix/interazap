<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas estendidas do módulo CRM: propostas, itens de proposta,
 * campos customizados, anotações, arquivos de negociação, eventos,
 * participantes, lembretes e vínculos de eventos a entidades.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_proposals')) {
            Schema::create('crm_proposals', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da proposta');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual a proposta pertence');
                $table->foreignUuid('crm_negotiation_id')->constrained('crm_negotiations')->comment('Negociação vinculada');
                $table->string('title', 255)->comment('Título da proposta');
                $table->integer('number')->nullable()->comment('Número da proposta');
                $table->decimal('total', 12, 2)->default(0)->comment('Valor total da proposta');
                $table->string('status', 20)->default('draft')->comment('Status: draft, sent, viewed, accepted, rejected');
                $table->date('valid_until')->nullable()->comment('Validade da proposta');
                $table->string('public_token', 255)->nullable()->comment('Token para acesso público');
                $table->text('notes')->nullable()->comment('Observações');
                $table->timestamp('sent_at')->nullable()->comment('Quando foi enviada');
                $table->timestamp('viewed_at')->nullable()->comment('Quando foi visualizada');
                $table->timestamp('accepted_at')->nullable()->comment('Quando foi aceita');
                $table->timestamp('rejected_at')->nullable()->comment('Quando foi rejeitada');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_proposals_tenant_id');
                $table->index('crm_negotiation_id', 'idx_crm_proposals_negotiation_id');
                $table->index('status', 'idx_crm_proposals_status');
                $table->index('public_token', 'idx_crm_proposals_public_token');
            });
        }

        if (!Schema::hasTable('crm_proposal_items')) {
            Schema::create('crm_proposal_items', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do item da proposta');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o item pertence');
                $table->foreignUuid('crm_proposal_id')->constrained('crm_proposals')->comment('Proposta vinculada');
                $table->foreignUuid('crm_product_id')->nullable()->constrained('crm_products')->comment('Produto de origem');
                $table->string('name', 255)->comment('Nome do item');
                $table->integer('quantity')->comment('Quantidade');
                $table->decimal('unit_price', 12, 2)->comment('Preço unitário');
                $table->decimal('discount', 12, 2)->default(0)->comment('Desconto aplicado');
                $table->decimal('total', 12, 2)->comment('Total do item');
                $table->integer('position')->default(0)->comment('Ordenação do item');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_proposal_items_tenant_id');
                $table->index('crm_proposal_id', 'idx_crm_proposal_items_proposal_id');
                $table->index('crm_product_id', 'idx_crm_proposal_items_product_id');
            });
        }

        if (!Schema::hasTable('crm_custom_fields')) {
            Schema::create('crm_custom_fields', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do campo customizado');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o campo pertence');
                $table->string('name', 255)->comment('Nome do campo');
                $table->string('type', 50)->comment('Tipo: text, number, date, select, multiselect');
                $table->string('entity', 50)->default('contact')->comment('Entidade alvo: company, contact, negotiation');
                $table->jsonb('options')->nullable()->comment('Opções para select ou multiselect');
                $table->boolean('is_required')->default(false)->comment('Se é obrigatório');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_custom_fields_tenant_id');
                $table->index('entity', 'idx_crm_custom_fields_entity');
            });

            DB::statement("ALTER TABLE crm_custom_fields ALTER COLUMN options TYPE json");
        }

        if (!Schema::hasTable('crm_custom_field_values')) {
            Schema::create('crm_custom_field_values', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do valor de campo customizado');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o valor pertence');
                $table->foreignUuid('crm_custom_field_id')->constrained('crm_custom_fields')->comment('Campo customizado de origem');
                $table->string('entity_type', 100)->comment('Tipo da entidade alvo (Morph)');
                $table->uuid('entity_id')->comment('ID da entidade alvo (Morph)');
                $table->text('value')->nullable()->comment('Valor armazenado');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_custom_field_values_tenant_id');
                $table->index('crm_custom_field_id', 'idx_crm_custom_field_values_field_id');
                $table->index(['entity_type', 'entity_id'], 'idx_crm_custom_field_values_morph');
            });
        }

        if (!Schema::hasTable('crm_notes')) {
            Schema::create('crm_notes', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da nota');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual a nota pertence');
                $table->string('entity_type', 100)->comment('Tipo da entidade anotada (Morph)');
                $table->uuid('entity_id')->comment('ID da entidade anotada (Morph)');
                $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users')->comment('Autor da nota');
                $table->text('content')->comment('Conteúdo da nota');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_notes_tenant_id');
                $table->index('auth_user_id', 'idx_crm_notes_user_id');
                $table->index(['entity_type', 'entity_id'], 'idx_crm_notes_morph');
            });
        }

        if (!Schema::hasTable('crm_negotiation_files')) {
            Schema::create('crm_negotiation_files', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do arquivo');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o arquivo pertence');
                $table->foreignUuid('crm_negotiation_id')->constrained('crm_negotiations')->comment('Negociação vinculada');
                $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users')->comment('Usuário que enviou o arquivo');
                $table->string('name', 255)->comment('Nome original do arquivo');
                $table->string('path', 500)->comment('Caminho de armazenamento');
                $table->bigInteger('size')->comment('Tamanho em bytes');
                $table->string('mime_type', 100)->nullable()->comment('Tipo MIME do arquivo');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_negotiation_files_tenant_id');
                $table->index('crm_negotiation_id', 'idx_crm_negotiation_files_negotiation_id');
                $table->index('auth_user_id', 'idx_crm_negotiation_files_user_id');
            });
        }

        if (!Schema::hasTable('crm_events')) {
            Schema::create('crm_events', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do evento');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o evento pertence');
                $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users')->comment('Criador do evento');
                $table->string('title', 255)->comment('Título do evento');
                $table->string('type', 50)->default('meeting')->comment('Tipo: meeting, call, task, reminder');
                $table->string('status', 20)->default('scheduled')->comment('Status do evento');
                $table->text('description')->nullable()->comment('Descrição do evento');
                $table->string('location', 255)->nullable()->comment('Local ou link do evento');
                $table->timestamp('starts_at')->comment('Início do evento');
                $table->timestamp('ends_at')->nullable()->comment('Término do evento');
                $table->boolean('is_all_day')->default(false)->comment('Se é evento de dia inteiro');
                $table->string('recurrence', 50)->default('none')->comment('Frequência de recorrência');
                $table->timestamp('recurrence_ends_at')->nullable()->comment('Fim da recorrência');
                $table->string('color', 7)->nullable()->comment('Cor no calendário');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id', 'idx_crm_events_tenant_id');
                $table->index('auth_user_id', 'idx_crm_events_user_id');
                $table->index('type', 'idx_crm_events_type');
                $table->index('status', 'idx_crm_events_status');
                $table->index('starts_at', 'idx_crm_events_starts_at');
            });
        }

        if (!Schema::hasTable('crm_event_links')) {
            Schema::create('crm_event_links', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do vínculo de evento');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o vínculo pertence');
                $table->foreignUuid('crm_event_id')->constrained('crm_events')->comment('Evento vinculado');
                $table->string('linkable_type', 100)->comment('Tipo da entidade vinculada (Morph)');
                $table->uuid('linkable_id')->comment('ID da entidade vinculada (Morph)');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_event_links_tenant_id');
                $table->index('crm_event_id', 'idx_crm_event_links_event_id');
                $table->index(['linkable_type', 'linkable_id'], 'idx_crm_event_links_morph');
            });
        }

        if (!Schema::hasTable('crm_event_participants')) {
            Schema::create('crm_event_participants', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do participante');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o participante pertence');
                $table->foreignUuid('crm_event_id')->constrained('crm_events')->comment('Evento vinculado');
                $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users')->comment('Usuário interno participante');
                $table->foreignUuid('crm_contact_id')->nullable()->constrained('crm_contacts')->comment('Contato externo participante');
                $table->string('name', 255)->nullable()->comment('Nome do participante se não vinculado');
                $table->string('email', 255)->nullable()->comment('E-mail do participante');
                $table->string('status', 20)->default('pending')->comment('Status de confirmação');
                $table->boolean('is_organizer')->default(false)->comment('Se é organizador do evento');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_event_participants_tenant_id');
                $table->index('crm_event_id', 'idx_crm_event_participants_event_id');
                $table->index('auth_user_id', 'idx_crm_event_participants_user_id');
                $table->index('crm_contact_id', 'idx_crm_event_participants_contact_id');
            });
        }

        if (!Schema::hasTable('crm_event_reminders')) {
            Schema::create('crm_event_reminders', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do lembrete');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o lembrete pertence');
                $table->foreignUuid('crm_event_id')->constrained('crm_events')->comment('Evento vinculado');
                $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users')->comment('Destinatário do lembrete');
                $table->string('type', 20)->default('notification')->comment('Tipo de lembrete');
                $table->integer('minutes_before')->default(0)->comment('Minutos antes do evento');
                $table->boolean('notify_ui')->default(true)->comment('Se notifica na interface');
                $table->boolean('notify_email')->default(false)->comment('Se notifica por e-mail');
                $table->boolean('notify_push')->default(false)->comment('Se notifica por push');
                $table->boolean('notify_whatsapp')->default(false)->comment('Se notifica por WhatsApp');
                $table->boolean('notify_webhook')->default(false)->comment('Se notifica por webhook');
                $table->timestamp('scheduled_at', 0)->nullable()->comment('Quando deve ser enviado');
                $table->boolean('is_sent')->default(false)->comment('Se já foi enviado');
                $table->timestamp('sent_at', 0)->nullable()->comment('Quando foi enviado');
                $table->timestamps(0);

                $table->index('tenant_id', 'idx_crm_event_reminders_tenant_id');
                $table->index('crm_event_id', 'idx_crm_event_reminders_event_id');
                $table->index('auth_user_id', 'idx_crm_event_reminders_user_id');
                $table->index('is_sent', 'idx_crm_event_reminders_is_sent');
                $table->index('scheduled_at', 'idx_crm_event_reminders_scheduled_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_event_reminders');
        Schema::dropIfExists('crm_event_participants');
        Schema::dropIfExists('crm_event_links');
        Schema::dropIfExists('crm_events');
        Schema::dropIfExists('crm_negotiation_files');
        Schema::dropIfExists('crm_notes');
        Schema::dropIfExists('crm_custom_field_values');
        Schema::dropIfExists('crm_custom_fields');
        Schema::dropIfExists('crm_proposal_items');
        Schema::dropIfExists('crm_proposals');
    }
};
