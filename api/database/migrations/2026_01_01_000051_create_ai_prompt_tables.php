<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI Domain Prompt Tables - Consolidated Migration
 *
 * Creates prompt management infrastructure:
 * - ai_prompt_masters: Master prompt templates
 * - ai_prompt_segments: Industry/segment specific prompts
 * - ai_prompt_plans: Plan-specific prompt overrides
 * - ai_prompt_tenants: Tenant-specific prompt customizations
 */
return new class extends Migration
{
    public function up(): void
    {
        // Create enum type for validation status (if not exists)
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'ai_prompt_validation_status') THEN
                    CREATE TYPE ai_prompt_validation_status AS ENUM ('pending', 'approved', 'rejected', 'quarantine');
                END IF;
            END
            $$
        ");

        // =====================================================================
        // AI_PROMPT_MASTERS - Base prompt templates (no tenant)
        // =====================================================================
        Schema::create('ai_prompt_masters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('content');
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // =====================================================================
        // AI_PROMPT_SEGMENTS - Industry/segment specific prompts
        // =====================================================================
        Schema::create('ai_prompt_segments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('master_id')->nullable();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('master_id')
                ->references('id')
                ->on('ai_prompt_masters')
                ->nullOnDelete();

            $table->index('is_active');
        });

        // Add FK from platform_tenants to ai_prompt_segments
        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->foreign('segment_id')
                ->references('id')
                ->on('ai_prompt_segments')
                ->nullOnDelete();
        });

        // =====================================================================
        // AI_PROMPT_PLANS - Plan-specific prompt configurations
        // =====================================================================
        Schema::create('ai_prompt_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('plan_id')->unique();
            $table->text('content');
            $table->jsonb('mandatory_rules')->nullable();
            $table->integer('token_limit_monthly')->nullable();
            $table->boolean('allow_overage')->default(false);
            $table->decimal('overage_price_per_1k', 10, 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('plan_id')
                ->references('id')
                ->on('platform_plans')
                ->cascadeOnDelete();

            $table->index('is_active');
        });

        // =====================================================================
        // AI_PROMPT_TENANTS - Tenant-specific customizations with validation
        // =====================================================================
        Schema::create('ai_prompt_tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->uuid('segment_id');
            $table->text('content');
            $table->text('previous_content')->nullable();
            $table->integer('version')->default(1);
            $table->text('validation_status')->default('pending'); // Using text, will add constraint
            $table->string('validated_hash', 64)->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->text('guardian_analysis')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('segment_id')
                ->references('id')
                ->on('ai_prompt_segments')
                ->restrictOnDelete();

            $table->index('validation_status');
            $table->index('is_active');
        });

        // Add check constraint for validation_status
        DB::statement("
            ALTER TABLE ai_prompt_tenants
            ADD CONSTRAINT chk_ai_prompt_tenants_validation_status
            CHECK (validation_status IN ('pending', 'approved', 'rejected', 'quarantine'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_tenants');
        Schema::dropIfExists('ai_prompt_plans');

        // Remove FK from platform_tenants
        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->dropForeign(['segment_id']);
        });

        Schema::dropIfExists('ai_prompt_segments');
        Schema::dropIfExists('ai_prompt_masters');

        // Drop enum type
        DB::statement('DROP TYPE IF EXISTS ai_prompt_validation_status');
    }
};
