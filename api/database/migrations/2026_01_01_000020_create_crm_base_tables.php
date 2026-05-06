<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas base do módulo CRM: empresas, contatos, telefones, tags,
 * produtos, motivos de perda, departamentos e tabelas pivô de relacionamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_companies')) {
            Schema::create('crm_companies', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da empresa');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual a empresa pertence');
                $table->string('name', 255)->comment('Nome ou razão social da empresa');
                $table->string('document', 32)->nullable()->comment('CNPJ ou CPF da empresa');
                $table->string('email', 255)->nullable()->comment('E-mail corporativo');
                $table->string('phone', 20)->nullable()->comment('Telefone principal');
                $table->string('address', 255)->nullable()->comment('Endereço completo');
                $table->string('city', 100)->nullable()->comment('Cidade');
                $table->string('state', 2)->nullable()->comment('UF');
                $table->string('zip_code', 10)->nullable()->comment('CEP');
                $table->boolean('is_active')->default(true)->comment('Se a empresa está ativa no CRM');
                $table->timestamps(0);
                $table->softDeletes();

                $table->index('tenant_id', 'idx_crm_companies_tenant_id');
                $table->index('document', 'idx_crm_companies_document');
                $table->index('name', 'idx_crm_companies_name');
            });
        }

        if (! Schema::hasTable('crm_contacts')) {
            Schema::create('crm_contacts', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do contato');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o contato pertence');
                $table->foreignUuid('crm_company_id')->nullable()->constrained('crm_companies')->comment('Empresa vinculada ao contato');
                $table->string('name', 255)->comment('Nome completo do contato');
                $table->string('email', 255)->nullable()->comment('E-mail pessoal ou profissional');
                $table->string('document', 32)->nullable()->comment('CPF do contato');
                $table->string('phone', 20)->nullable()->comment('Telefone principal');
                $table->string('whatsapp', 20)->nullable()->comment('Número do WhatsApp');
                $table->string('position', 255)->nullable()->comment('Cargo na empresa');
                $table->string('avatar_url', 500)->nullable()->comment('URL da foto do contato');
                $table->text('notes')->nullable()->comment('Observações sobre o contato');
                $table->jsonb('custom_fields')->nullable()->comment('Campos customizados dinâmicos');
                $table->boolean('is_active')->default(true)->comment('Se o contato está ativo');
                $table->timestamps(0);
                $table->softDeletes();

                $table->index('tenant_id', 'idx_crm_contacts_tenant_id');
                $table->index('crm_company_id', 'idx_crm_contacts_company_id');
                $table->index('name', 'idx_crm_contacts_name');
            });
        }

        if (! Schema::hasTable('crm_contact_phones')) {
            Schema::create('crm_contact_phones', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do telefone');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o telefone pertence');
                $table->foreignUuid('crm_contact_id')->constrained('crm_contacts')->comment('Contato dono do telefone');
                $table->string('label', 50)->nullable()->comment('Rótulo do telefone (Celular, Trabalho, Casa)');
                $table->string('phone_e164', 20)->comment('Telefone no formato E.164');
                $table->boolean('is_primary')->default(false)->comment('Se é o telefone principal do contato');
                $table->timestamp('valid_from', 0)->nullable()->comment('Data de início da validade');
                $table->timestamp('valid_to', 0)->nullable()->comment('Data de fim da validade');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_contact_phones_tenant_id');
                $table->index('crm_contact_id', 'idx_crm_contact_phones_contact_id');
                $table->index('phone_e164', 'idx_crm_contact_phones_phone_e164');
            });
        }

        if (! Schema::hasTable('crm_company_contacts')) {
            Schema::create('crm_company_contacts', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da relação empresa-contato');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual a relação pertence');
                $table->foreignUuid('crm_company_id')->constrained('crm_companies')->comment('Empresa vinculada');
                $table->foreignUuid('crm_contact_id')->constrained('crm_contacts')->comment('Contato vinculado');
                $table->timestamps();

                $table->unique(['tenant_id', 'crm_company_id', 'crm_contact_id'], 'uq_crm_company_contacts');
                $table->index('tenant_id', 'idx_crm_company_contacts_tenant_id');
                $table->index('crm_company_id', 'idx_crm_company_contacts_company_id');
                $table->index('crm_contact_id', 'idx_crm_company_contacts_contact_id');
            });
        }

        if (! Schema::hasTable('crm_tags')) {
            Schema::create('crm_tags', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da tag');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual a tag pertence');
                $table->string('name', 100)->comment('Nome da tag');
                $table->string('color', 7)->nullable()->comment('Cor em hexadecimal (#FF0000)');
                $table->string('category', 50)->nullable()->comment('Categoria da tag');
                $table->boolean('is_active')->default(true)->comment('Se a tag está ativa');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_tags_tenant_id');
                $table->index('name', 'idx_crm_tags_name');
            });
        }

        if (! Schema::hasTable('crm_products')) {
            Schema::create('crm_products', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do produto');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o produto pertence');
                $table->string('name', 255)->comment('Nome do produto ou serviço');
                $table->text('description')->nullable()->comment('Descrição detalhada do produto');
                $table->string('type', 50)->default('product')->comment('Tipo: product ou service');
                $table->decimal('price', 12, 2)->default(0)->comment('Preço de venda');
                $table->decimal('cost', 12, 2)->nullable()->comment('Custo de aquisição ou produção');
                $table->string('code', 100)->nullable()->comment('Código ou SKU do produto');
                $table->string('unit', 20)->nullable()->comment('Unidade de medida (un, kg, hr)');
                $table->integer('stock_quantity')->default(0)->comment('Quantidade em estoque');
                $table->integer('min_stock')->default(0)->comment('Estoque mínimo');
                $table->boolean('is_featured')->default(false)->comment('Se é destaque');
                $table->boolean('is_active')->default(true)->comment('Se o produto está ativo');
                $table->integer('stock')->default(0)->comment('Quantidade em estoque (legado)');
                $table->boolean('track_stock')->default(false)->comment('Se controla estoque');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_products_tenant_id');
                $table->index('code', 'idx_crm_products_code');
                $table->index('name', 'idx_crm_products_name');
            });
        }

        if (! Schema::hasTable('crm_reason_losses')) {
            Schema::create('crm_reason_losses', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do motivo de perda');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o motivo pertence');
                $table->string('name', 255)->comment('Nome do motivo de perda');
                $table->text('description')->nullable()->comment('Descrição do motivo');
                $table->boolean('requires_comment')->default(false)->comment('Se exige comentário ao usar');
                $table->boolean('is_active')->default(true)->comment('Se o motivo está ativo');
                $table->integer('position')->default(0)->comment('Ordem de exibição');
                $table->integer('usage_count')->default(0)->comment('Contador de uso');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_reason_losses_tenant_id');
                $table->index('position', 'idx_crm_reason_losses_position');
            });
        }

        if (! Schema::hasTable('crm_departments')) {
            Schema::create('crm_departments', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do departamento');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual o departamento pertence');
                $table->string('name', 255)->comment('Nome do departamento');
                $table->text('description')->nullable()->comment('Descrição do departamento');
                $table->boolean('is_active')->default(true)->comment('Se o departamento está ativo');
                $table->timestamps();

                $table->index('tenant_id', 'idx_crm_departments_tenant_id');
                $table->index('name', 'idx_crm_departments_name');
            });
        }

        if (! Schema::hasTable('crm_contact_tags')) {
            Schema::create('crm_contact_tags', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da relação contato-tag');
                $table->foreignUuid('tenant_id')->nullable()->constrained('platform_tenants')->comment('Tenant ao qual a relação pertence');
                $table->foreignUuid('crm_contact_id')->constrained('crm_contacts')->comment('Contato vinculado');
                $table->foreignUuid('crm_tag_id')->constrained('crm_tags')->comment('Tag vinculada');
                $table->timestamps();

                $table->unique(['tenant_id', 'crm_contact_id', 'crm_tag_id'], 'uq_crm_contact_tags');
                $table->index('tenant_id', 'idx_crm_contact_tags_tenant_id');
                $table->index('crm_contact_id', 'idx_crm_contact_tags_contact_id');
                $table->index('crm_tag_id', 'idx_crm_contact_tags_tag_id');
            });
        }

        if (! Schema::hasTable('crm_company_tags')) {
            Schema::create('crm_company_tags', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da relação empresa-tag');
                $table->foreignUuid('tenant_id')->constrained('platform_tenants')->comment('Tenant ao qual a relação pertence');
                $table->foreignUuid('crm_company_id')->constrained('crm_companies')->comment('Empresa vinculada');
                $table->foreignUuid('crm_tag_id')->constrained('crm_tags')->comment('Tag vinculada');
                $table->timestamps();

                $table->unique(['tenant_id', 'crm_company_id', 'crm_tag_id'], 'uq_crm_company_tags');
                $table->index('tenant_id', 'idx_crm_company_tags_tenant_id');
                $table->index('crm_company_id', 'idx_crm_company_tags_company_id');
                $table->index('crm_tag_id', 'idx_crm_company_tags_tag_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_company_tags');
        Schema::dropIfExists('crm_contact_tags');
        Schema::dropIfExists('crm_departments');
        Schema::dropIfExists('crm_reason_losses');
        Schema::dropIfExists('crm_products');
        Schema::dropIfExists('crm_tags');
        Schema::dropIfExists('crm_company_contacts');
        Schema::dropIfExists('crm_contact_phones');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_companies');
    }
};
