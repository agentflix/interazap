<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas de funil e negociação do módulo CRM: funis, etapas,
 * negociações, tarefas, produtos vinculados e tags de negociação.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_negotiation_funnels')) {
            Schema::create('crm_negotiation_funnels', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do funil');
                $table->foreignUuid('tenant_id')->comment('Tenant ao qual o funil pertence')->constrained('platform_tenants');
                $table->string('name', 255)->comment('Nome do funil');
                $table->text('description')->nullable()->comment('Descrição do funil de vendas');
                $table->boolean('is_active')->default(true)->comment('Se o funil está ativo');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_negotiation_funnels_tenant_id');
                $table->index('name', 'idx_crm_negotiation_funnels_name');
            });
        }

        if (! Schema::hasTable('crm_negotiation_funnel_steps')) {
            Schema::create('crm_negotiation_funnel_steps', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da etapa do funil');
                $table->foreignUuid('tenant_id')->comment('Tenant ao qual a etapa pertence')->constrained('platform_tenants');
                $table->foreignUuid('crm_negotiation_funnel_id')->comment('Funil dono da etapa')->constrained('crm_negotiation_funnels');
                $table->string('name', 255)->comment('Nome da etapa');
                $table->string('color', 7)->nullable()->comment('Cor da etapa');
                $table->boolean('is_active')->default(true)->comment('Se a etapa está ativa');
                $table->integer('order')->default(0)->comment('Ordem da etapa no funil');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_negotiation_funnel_steps_tenant_id');
                $table->index('crm_negotiation_funnel_id', 'idx_crm_funnel_steps_funnel_id');
                $table->index('order', 'idx_crm_negotiation_funnel_steps_order');
            });
        }

        if (! Schema::hasTable('crm_negotiations')) {
            Schema::create('crm_negotiations', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da negociação');
                $table->foreignUuid('tenant_id')->comment('Tenant ao qual a negociação pertence')->constrained('platform_tenants');
                $table->foreignUuid('crm_company_id')->nullable()->comment('Empresa vinculada')->constrained('crm_companies');
                $table->foreignUuid('crm_contact_id')->comment('Contato vinculado')->constrained('crm_contacts');
                $table->foreignUuid('crm_negotiation_funnel_id')->comment('Funil de vendas')->constrained('crm_negotiation_funnels');
                $table->foreignUuid('crm_negotiation_funnel_step_id')->comment('Etapa atual no funil')->constrained('crm_negotiation_funnel_steps');
                $table->foreignUuid('crm_reason_loss_id')->nullable()->comment('Motivo de perda (se perdida)')->constrained('crm_reason_losses');
                $table->foreignUuid('auth_user_id')->nullable()->comment('Responsável pela negociação')->constrained('auth_users');
                $table->string('title', 255)->comment('Título da negociação');
                $table->decimal('amount', 12, 2)->default(0)->comment('Valor estimado');
                $table->string('status', 20)->default('open')->comment('Status: open, won, lost, paused');
                $table->smallInteger('lead_score')->default(0)->comment('Score do lead (0-100)');
                $table->integer('position')->default(0)->comment('Posição no Kanban');
                $table->date('expected_close')->nullable()->comment('Data prevista de fechamento');
                $table->timestamp('closed_at', 0)->nullable()->comment('Quando foi fechada');
                $table->text('notes')->nullable()->comment('Observações da negociação');
                $table->timestamps(0);
                $table->softDeletes();

                $table->index('tenant_id', 'idx_crm_negotiations_tenant_id');
                $table->index('crm_company_id', 'idx_crm_negotiations_company_id');
                $table->index('crm_contact_id', 'idx_crm_negotiations_contact_id');
                $table->index('crm_negotiation_funnel_id', 'idx_crm_negotiations_funnel_id');
                $table->index('crm_negotiation_funnel_step_id', 'idx_crm_negotiations_step_id');
                $table->index('auth_user_id', 'idx_crm_negotiations_user_id');
                $table->index('status', 'idx_crm_negotiations_status');
                $table->index('expected_close', 'idx_crm_negotiations_expected_close');
            });
        }

        if (! Schema::hasTable('crm_negotiation_tasks')) {
            Schema::create('crm_negotiation_tasks', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da tarefa');
                $table->foreignUuid('tenant_id')->comment('Tenant ao qual a tarefa pertence')->constrained('platform_tenants');
                $table->foreignUuid('crm_negotiation_id')->comment('Negociação vinculada')->constrained('crm_negotiations');
                $table->foreignUuid('auth_user_id')->nullable()->comment('Responsável pela tarefa')->constrained('auth_users');
                $table->string('title', 255)->comment('Título da tarefa');
                $table->text('description')->nullable()->comment('Descrição da tarefa');
                $table->timestamp('due_date')->nullable()->comment('Data de vencimento');
                $table->string('status', 20)->default('open')->comment('Status: pending, done, overdue');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_negotiation_tasks_tenant_id');
                $table->index('crm_negotiation_id', 'idx_crm_negotiation_tasks_negotiation_id');
                $table->index('auth_user_id', 'idx_crm_negotiation_tasks_user_id');
                $table->index('due_date', 'idx_crm_negotiation_tasks_due_date');
                $table->index('status', 'idx_crm_negotiation_tasks_status');
            });
        }

        if (! Schema::hasTable('crm_negotiation_products')) {
            Schema::create('crm_negotiation_products', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do produto na negociação');
                $table->foreignUuid('tenant_id')->comment('Tenant ao qual o registro pertence')->constrained('platform_tenants');
                $table->foreignUuid('crm_negotiation_id')->comment('Negociação vinculada')->constrained('crm_negotiations');
                $table->foreignUuid('crm_product_id')->nullable()->comment('Produto de origem')->constrained('crm_products');
                $table->string('name', 255)->comment('Nome do produto no momento da venda');
                $table->integer('quantity')->comment('Quantidade');
                $table->decimal('unit_price', 12, 2)->comment('Preço unitário');
                $table->decimal('total', 12, 2)->comment('Total (quantity * unit_price)');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_negotiation_products_tenant_id');
                $table->index('crm_negotiation_id', 'idx_crm_negotiation_products_negotiation_id');
                $table->index('crm_product_id', 'idx_crm_negotiation_products_product_id');
            });
        }

        if (! Schema::hasTable('crm_negotiation_tags')) {
            Schema::create('crm_negotiation_tags', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da relação negociação-tag');
                $table->foreignUuid('tenant_id')->comment('Tenant ao qual a relação pertence')->constrained('platform_tenants');
                $table->foreignUuid('crm_negotiation_id')->comment('Negociação vinculada')->constrained('crm_negotiations');
                $table->foreignUuid('crm_tag_id')->comment('Tag vinculada')->constrained('crm_tags');
                $table->timestamps();

                $table->unique(['tenant_id', 'crm_negotiation_id', 'crm_tag_id'], 'uq_crm_negotiation_tags');
                $table->index('tenant_id', 'idx_crm_negotiation_tags_tenant_id');
                $table->index('crm_negotiation_id', 'idx_crm_negotiation_tags_negotiation_id');
                $table->index('crm_tag_id', 'idx_crm_negotiation_tags_tag_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_negotiation_tags');
        Schema::dropIfExists('crm_negotiation_products');
        Schema::dropIfExists('crm_negotiation_tasks');
        Schema::dropIfExists('crm_negotiations');
        Schema::dropIfExists('crm_negotiation_funnel_steps');
        Schema::dropIfExists('crm_negotiation_funnels');
    }
};
