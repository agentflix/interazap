<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas de prompt management do contexto AI.
 *
 * Tabelas criadas:
 * - ai_prompt_masters: Prompts base globais (cross-tenant)
 * - ai_prompt_segments: Segmentos de negócio vinculados a masters
 * - ai_prompt_plans: Prompts e regras específicas por plano
 * - ai_prompt_tenants: Customização de prompt por tenant
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_prompt_masters')) {
            return;
        }

        $this->createPromptMasters();
        $this->createPromptSegments();
        $this->createPromptPlans();
        $this->createPromptTenants();
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_tenants');
        Schema::dropIfExists('ai_prompt_plans');
        Schema::dropIfExists('ai_prompt_segments');
        Schema::dropIfExists('ai_prompt_masters');
    }

    private function createPromptMasters(): void
    {
        Schema::create('ai_prompt_masters', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do prompt master (UUID)');
            $table->string('name', 255)->comment('Nome do prompt master');
            $table->text('content')->comment('Conteúdo base do prompt');
            $table->integer('version')->default(1)->comment('Versão do prompt master');
            $table->boolean('is_active')->default(true)->comment('Indica se o prompt master está ativo');
            $table->timestamps();

            $table->index(['is_active'], 'idx_ai_prompt_masters_is_active');
            $table->index(['name'], 'idx_ai_prompt_masters_name');
        });
    }

    private function createPromptSegments(): void
    {
        Schema::create('ai_prompt_segments', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do segmento (UUID)');
            $table->uuid('master_id')->nullable()->comment('Prompt master vinculado');
            $table->string('code', 100)->unique()->comment('Código único do segmento de negócio');
            $table->string('name', 255)->comment('Nome do segmento');
            $table->text('description')->nullable()->comment('Descrição do segmento de negócio');
            $table->text('content')->comment('Conteúdo do prompt para este segmento');
            $table->boolean('is_active')->default(true)->comment('Indica se o segmento está ativo');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('master_id', 'fk_ai_prompt_segments_master_id')
                ->references('id')
                ->on('ai_prompt_masters');
            $table->index(['master_id'], 'idx_ai_prompt_segments_master_id');
            $table->index(['code'], 'idx_ai_prompt_segments_code');
            $table->index(['is_active'], 'idx_ai_prompt_segments_is_active');
        });
    }

    private function createPromptPlans(): void
    {
        Schema::create('ai_prompt_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do plano de prompt (UUID)');
            $table->uuid('plan_id')->comment('Plano da plataforma vinculado');
            $table->text('content')->comment('Prompt específico do plano');
            $table->jsonb('mandatory_rules')->nullable()->comment('Regras obrigatórias do plano');
            $table->integer('token_limit_monthly')->nullable()->comment('Limite mensal de tokens');
            $table->boolean('allow_overage')->default(false)->comment('Permite excedente de tokens');
            $table->decimal('overage_price_per_1k', 10, 6)->nullable()->comment('Preço por 1K tokens excedente');
            $table->boolean('is_active')->default(true)->comment('Indica se o plano de prompt está ativo');
            $table->timestamps();

            $table->foreign('plan_id', 'fk_ai_prompt_plans_plan_id')
                ->references('id')
                ->on('platform_plans');
            $table->index(['plan_id'], 'idx_ai_prompt_plans_plan_id');
            $table->index(['is_active'], 'idx_ai_prompt_plans_is_active');
        });
    }

    private function createPromptTenants(): void
    {
        Schema::create('ai_prompt_tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único da customização (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono da customização');
            $table->uuid('segment_id')->comment('Segmento de negócio vinculado');
            $table->text('content')->comment('Conteúdo do prompt');
            $table->text('previous_content')->nullable()->comment('Conteúdo anterior do prompt');
            $table->integer('version')->default(1)->comment('Versão do prompt');
            $table->text('validation_status')->default('pending')->comment('Status de validação');
            $table->string('validated_hash', 64)->nullable()->comment('Hash validado');
            $table->timestamp('validated_at', 0)->nullable()->comment('Data de validação');
            $table->text('guardian_analysis')->nullable()->comment('Análise do guardian');
            $table->boolean('is_active')->default(true)->comment('Indica se está ativo');
            $table->timestamps(0);

            $table->foreign('tenant_id', 'fk_ai_prompt_tenants_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('segment_id', 'fk_ai_prompt_tenants_segment_id')
                ->references('id')
                ->on('ai_prompt_segments');
            $table->index(['tenant_id', 'segment_id'], 'idx_ai_prompt_tenants_tenant_segment');
            $table->unique(['tenant_id', 'segment_id'], 'uniq_ai_prompt_tenants_tenant_segment');
        });
    }
};
