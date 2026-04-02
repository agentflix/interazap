<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Domain Tables - Consolidated Migration
 *
 * Creates core platform infrastructure:
 * - platform_tenants: Multi-tenant isolation base
 * - platform_plans: Subscription plans
 * - platform_uazapi_instances: UAZAPI WhatsApp integration
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // PLATFORM_TENANTS - Core tenant table
        // =====================================================================
        Schema::create('platform_tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('tenant_code', 12)->unique();
            $table->string('primary_email')->nullable();
            $table->string('document', 32)->nullable();

            // Billing integration
            $table->string('asaas_customer_id', 120)->nullable();
            $table->uuid('billing_webhook_token')->unique();

            // Contact information
            $table->string('phone', 20)->nullable();

            // Address
            $table->string('street', 255)->nullable();
            $table->string('number', 20)->nullable();
            $table->string('complement', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip_code', 20)->nullable();

            // AI Segment (FK added later after ai_prompt_segments created)
            $table->uuid('segment_id')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'created_at']);
        });

        // =====================================================================
        // PLATFORM_PLANS - Subscription plans
        // =====================================================================
        Schema::create('platform_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 50)->unique();

            // Limits
            $table->unsignedInteger('limit_users');
            $table->string('storage_mode', 20); // unlimited, limited
            $table->unsignedBigInteger('storage_limit_bytes')->nullable();
            $table->boolean('ai_enabled');
            $table->unsignedInteger('whatsapp_integrations_limit');
            $table->string('negotiations_mode', 20); // unlimited, limited
            $table->unsignedInteger('negotiations_limit')->nullable();

            // Pricing
            $table->decimal('price_monthly', 10, 2);
            $table->string('asaas_product_id', 100)->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'created_at']);
        });

        // =====================================================================
        // PLATFORM_UAZAPI_INSTANCES - UAZAPI WhatsApp integration
        // =====================================================================
        Schema::create('platform_uazapi_instances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('system_name')->nullable();
            $table->string('token')->unique();
            $table->string('status', 50)->default('disconnected');
            $table->string('webhook_url')->nullable();
            $table->string('admin_field_01')->nullable();
            $table->string('admin_field_02')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_status_at')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            // Indexes
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_uazapi_instances');
        Schema::dropIfExists('platform_plans');
        Schema::dropIfExists('platform_tenants');
    }
};
