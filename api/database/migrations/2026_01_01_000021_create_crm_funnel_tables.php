<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Domain Funnel Tables - Consolidated Migration
 *
 * Creates sales funnel entities:
 * - crm_negotiation_funnels: Funnel definitions
 * - crm_negotiation_funnel_steps: Funnel stages
 * - crm_negotiations: Deal/opportunity records
 * - crm_negotiation_tasks: Tasks linked to negotiations
 * - crm_negotiation_products: Products in negotiations
 * - crm_negotiation_tags: Tag pivot
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // CRM_NEGOTIATION_FUNNELS
        // =====================================================================
        Schema::create('crm_negotiation_funnels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'name']);
        });

        // =====================================================================
        // CRM_NEGOTIATION_FUNNEL_STEPS
        // =====================================================================
        Schema::create('crm_negotiation_funnel_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_negotiation_funnel_id');
            $table->string('name');
            $table->string('color', 7)->nullable(); // #RRGGBB
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_negotiation_funnel_id')
                ->references('id')
                ->on('crm_negotiation_funnels')
                ->cascadeOnDelete();

            $table->unique(
                ['tenant_id', 'crm_negotiation_funnel_id', 'order'],
                'crm_funnel_steps_order_unique'
            );
            $table->unique(
                ['id', 'crm_negotiation_funnel_id'],
                'crm_funnel_steps_id_funnel_unique'
            );
        });

        // =====================================================================
        // CRM_NEGOTIATIONS
        // =====================================================================
        Schema::create('crm_negotiations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_company_id')->nullable();
            $table->uuid('crm_contact_id');
            $table->uuid('crm_negotiation_funnel_id');
            $table->uuid('crm_negotiation_funnel_step_id');
            $table->uuid('crm_reason_loss_id')->nullable();
            $table->string('title');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20)->default('open'); // open, won, lost
            $table->unsignedTinyInteger('lead_score')->default(0);
            $table->integer('position')->default(0);
            $table->date('expected_close')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_company_id')
                ->references('id')
                ->on('crm_companies')
                ->nullOnDelete();

            $table->foreign('crm_contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->cascadeOnDelete();

            $table->foreign('crm_negotiation_funnel_id')
                ->references('id')
                ->on('crm_negotiation_funnels')
                ->cascadeOnDelete();

            $table->foreign(['crm_negotiation_funnel_step_id', 'crm_negotiation_funnel_id'])
                ->references(['id', 'crm_negotiation_funnel_id'])
                ->on('crm_negotiation_funnel_steps')
                ->cascadeOnDelete();

            $table->foreign('crm_reason_loss_id')
                ->references('id')
                ->on('crm_reason_losses')
                ->nullOnDelete();

            // Performance indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'crm_negotiation_funnel_step_id'], 'idx_negotiations_tenant_step');
            $table->index(['tenant_id', 'crm_contact_id']);
            $table->index(['tenant_id', 'status', 'position']);
        });

        // Check constraint for positive amount
        DB::statement('ALTER TABLE crm_negotiations ADD CONSTRAINT chk_crm_negotiations_amount_positive CHECK (amount >= 0)');

        // =====================================================================
        // CRM_NEGOTIATION_TASKS
        // =====================================================================
        Schema::create('crm_negotiation_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_negotiation_id');
            $table->uuid('auth_user_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->string('status', 20)->default('open'); // open, done
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_negotiation_id')
                ->references('id')
                ->on('crm_negotiations')
                ->cascadeOnDelete();

            $table->foreign('auth_user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            $table->index('auth_user_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'due_date']);
        });

        // =====================================================================
        // CRM_NEGOTIATION_PRODUCTS
        // =====================================================================
        Schema::create('crm_negotiation_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_negotiation_id');
            $table->uuid('crm_product_id')->nullable();
            $table->string('name');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_negotiation_id')
                ->references('id')
                ->on('crm_negotiations')
                ->cascadeOnDelete();

            $table->foreign('crm_product_id')
                ->references('id')
                ->on('crm_products')
                ->nullOnDelete();

            $table->index(['tenant_id', 'crm_negotiation_id']);
        });

        // =====================================================================
        // CRM_NEGOTIATION_TAGS
        // =====================================================================
        Schema::create('crm_negotiation_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_negotiation_id');
            $table->uuid('crm_tag_id');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_negotiation_id')
                ->references('id')
                ->on('crm_negotiations')
                ->cascadeOnDelete();

            $table->foreign('crm_tag_id')
                ->references('id')
                ->on('crm_tags')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'crm_negotiation_id', 'crm_tag_id']);
        });
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
