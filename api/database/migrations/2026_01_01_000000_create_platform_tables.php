<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas do contexto Platform (multi-tenancy, planos, instâncias UazAPI, leads, catálogos de bootstrap).
 *
 * Tabelas criadas:
 * - platform_plans
 * - platform_tenants
 * - platform_uazapi_instances
 * - platform_leads
 * - platform_tenant_bootstrap_catalogs
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_plans')) {
            DB::statement('DROP TYPE IF EXISTS platform_reports_mode');
            DB::statement("CREATE TYPE platform_reports_mode AS ENUM ('BASIC', 'ADVANCED', 'FULL')");

            Schema::create('platform_plans', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do plano (UUID v7)');
                $table->string('name', 100)->comment('Nome comercial do plano');
                $table->string('slug', 100)->unique()->comment('Slug URL-friendly para o plano');
                $table->integer('limit_users')->comment('Limite máximo de usuários permitidos no plano');
                $table->string('storage_mode', 20)->default('shared')->comment('Modo de armazenamento: shared, dedicated, unlimited');
                $table->bigInteger('storage_limit_bytes')->nullable()->comment('Limite de armazenamento em bytes (null = ilimitado)');
                $table->boolean('ai_enabled')->default(false)->comment('Se recursos de IA estão habilitados no plano');
                $table->integer('chat_channels_limit')->default(1)->comment('Quantidade máxima de canais de chat permitidos');
                $table->string('negotiations_mode', 20)->default('basic')->comment('Modo de negociações: basic, advanced, full');
                $table->integer('negotiations_limit')->nullable()->comment('Limite de negociações simultâneas (null = ilimitado)');
                $table->string('reports_mode', 20)->default('basic')->comment('Modo de relatórios: basic, advanced, full');
                $table->decimal('price_monthly', 10, 2)->comment('Preço mensal em reais');
                $table->string('asaas_product_id', 100)->nullable()->comment('ID do produto correspondente no Asaas');
                $table->boolean('is_active')->default(true)->comment('Se o plano está disponível para contratação');
                $table->timestamps();
                $table->softDeletes();

                $table->index('slug', 'idx_platform_plans_slug');
                $table->index('is_active', 'idx_platform_plans_is_active');
            });

            DB::statement('ALTER TABLE platform_plans ALTER COLUMN reports_mode DROP DEFAULT');
            DB::statement('ALTER TABLE platform_plans ALTER COLUMN reports_mode TYPE platform_reports_mode USING UPPER(reports_mode)::platform_reports_mode');
            DB::statement("ALTER TABLE platform_plans ALTER COLUMN reports_mode SET DEFAULT 'BASIC'");
        }

        if (! Schema::hasTable('platform_tenants')) {
            Schema::create('platform_tenants', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do tenant (UUID v7)');
                $table->string('name', 255)->comment('Nome fantasia do tenant');
                $table->string('tenant_code', 12)->unique()->comment('Código único do tenant para URLs e API');
                $table->string('primary_email', 255)->nullable()->comment('E-mail principal para contato e faturamento');
                $table->string('document', 32)->nullable()->comment('CPF ou CNPJ do tenant');
                $table->string('asaas_customer_id', 120)->nullable()->comment('ID do cliente no gateway Asaas');
                $table->uuid('billing_webhook_token')->comment('Token UUID para autenticação de webhooks de billing');
                $table->string('phone', 20)->nullable()->comment('Telefone principal do tenant');
                $table->string('street', 255)->nullable()->comment('Rua do endereço do tenant');
                $table->string('number', 20)->nullable()->comment('Número do endereço do tenant');
                $table->string('complement', 120)->nullable()->comment('Complemento do endereço do tenant');
                $table->string('district', 120)->nullable()->comment('Bairro do tenant');
                $table->string('city', 120)->nullable()->comment('Cidade do tenant');
                $table->string('state', 2)->nullable()->comment('UF do tenant');
                $table->string('zip_code', 20)->nullable()->comment('CEP do tenant');
                $table->uuid('segment_id')->nullable()->comment('Segmento de negócio vinculado (FK -> ai_prompt_segments)');
                $table->boolean('is_active')->default(true)->comment('Se o tenant está ativo na plataforma');
                $table->timestamps(0);
                $table->softDeletes();
                $table->uuid('plan_id')->comment('Plano contratado pelo tenant (FK -> platform_plans)');
                $table->string('billing_status', 20)->default('active')->comment('Status de faturamento: active, locked, grace, purge');
                $table->timestamp('billing_locked_at', 0)->nullable()->comment('Quando o tenant foi bloqueado por inadimplência');
                $table->string('billing_lock_reason', 100)->nullable()->comment('Motivo do bloqueio de faturamento');
                $table->date('billing_grace_deadline')->nullable()->comment('Prazo final do período de carência');
                $table->date('billing_purge_deadline')->nullable()->comment('Prazo para purge definitivo de dados');
                $table->timestamp('last_collection_sent_at', 0)->nullable()->comment('Data do último envio de cobrança automática');
                $table->integer('collection_count')->default(0)->comment('Quantidade total de cobranças enviadas');
                $table->boolean('media_transcription_audio_enabled')->default(false)->comment('Se a transcrição de áudio está ativada');
                $table->boolean('media_transcription_image_enabled')->default(false)->comment('Se a transcrição de imagem está ativada');
                $table->boolean('media_transcription_video_enabled')->default(false)->comment('Se a transcrição de vídeo está ativada');
                $table->integer('media_transcription_audio_max_minutes')->default(5)->comment('Duração máxima de áudio em minutos');
                $table->integer('media_transcription_image_max_per_message')->default(3)->comment('Máximo de imagens por mensagem');
                $table->integer('media_transcription_video_max_seconds')->default(30)->comment('Duração máxima de vídeo em segundos');
                $table->jsonb('settings_localization')->default('{}')->comment('Configurações de localização e idioma');
                $table->jsonb('settings_privacy')->default('{}')->comment('Configurações de privacidade e conformidade LGPD');
                $table->jsonb('settings_chat')->nullable()->comment('Configurações específicas do módulo de chat');

                $table->index('plan_id', 'idx_platform_tenants_plan_id');
                $table->index('segment_id', 'idx_platform_tenants_segment_id');
                $table->index('tenant_code', 'idx_platform_tenants_tenant_code');
                $table->index('billing_status', 'idx_platform_tenants_billing_status');
                $table->index('is_active', 'idx_platform_tenants_is_active');

                $table->foreign('plan_id')->references('id')->on('platform_plans')->onDelete('restrict');
                // FK segment_id -> ai_prompt_segments adicionada em migration posterior (cross-domain)
            });
        }

        if (! Schema::hasTable('platform_uazapi_instances')) {
            Schema::create('platform_uazapi_instances', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da instância UazAPI');
                $table->uuid('tenant_id')->comment('Tenant proprietário da instância (FK -> platform_tenants)');
                $table->string('name', 255)->comment('Nome descritivo da instância');
                $table->string('system_name', 100)->nullable()->comment('Nome técnico usado pelo sistema');
                $table->string('token', 255)->comment('Token de autenticação na UazAPI');
                $table->string('status', 20)->default('inactive')->comment('Status atual: inactive, connecting, active, error');
                $table->string('webhook_url', 500)->nullable()->comment('URL para recebimento de webhooks da UazAPI');
                $table->jsonb('config')->default('{}')->comment('Configurações consolidadas da instância (antigos admin_field_01/02)');
                $table->jsonb('metadata')->nullable()->comment('Metadados diversos da instância');
                $table->timestamp('last_status_at')->nullable()->comment('Data/hora da última verificação de status');
                $table->timestamps();

                $table->index('tenant_id', 'idx_platform_uazapi_tenant_id');
                $table->index('status', 'idx_platform_uazapi_status');

                $table->foreign('tenant_id')->references('id')->on('platform_tenants')->onDelete('cascade');
            });

            DB::statement('ALTER TABLE platform_uazapi_instances ALTER COLUMN metadata TYPE json');
        }

        if (! Schema::hasTable('platform_leads')) {
            Schema::create('platform_leads', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do lead');
                $table->string('name', 255)->comment('Nome completo do lead');
                $table->string('phone', 20)->comment('Telefone de contato');
                $table->string('email', 255)->comment('E-mail de contato');
                $table->string('company', 255)->nullable()->comment('Empresa do lead');
                $table->string('utm_source', 100)->nullable()->comment('UTM source da campanha');
                $table->string('utm_medium', 100)->nullable()->comment('UTM medium da campanha');
                $table->string('utm_campaign', 100)->nullable()->comment('UTM campaign da campanha');
                $table->text('referrer')->nullable()->comment('URL de origem/referrer');
                $table->string('user_agent', 1023)->nullable()->comment('User agent do navegador');
                $table->string('ip_address', 45)->nullable()->comment('Endereço IP de origem (IPv6 ready)');
                $table->boolean('lgpd_consent')->default(false)->comment('Consentimento LGPD fornecido');
                $table->timestamps();

                $table->index('email', 'idx_platform_leads_email');
            });
        }

        if (! Schema::hasTable('platform_tenant_bootstrap_catalogs')) {
            Schema::create('platform_tenant_bootstrap_catalogs', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do catálogo de bootstrap');
                $table->string('segment_code', 50)->comment('Código do segmento de negócio');
                $table->jsonb('payload')->comment('Dados estruturados do catálogo para provisionamento');
                $table->boolean('is_active')->default(true)->comment('Se o catálogo está ativo para novos tenants');
                $table->timestamps();

                $table->index('segment_code', 'idx_platform_bootstrap_segment_code');
                $table->index('is_active', 'idx_platform_bootstrap_is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_tenant_bootstrap_catalogs');
        Schema::dropIfExists('platform_leads');
        Schema::dropIfExists('platform_uazapi_instances');
        Schema::dropIfExists('platform_tenants');
        Schema::dropIfExists('platform_plans');
    }
};
