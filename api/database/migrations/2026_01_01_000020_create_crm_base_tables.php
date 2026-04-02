<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Domain Base Tables - Consolidated Migration
 *
 * Creates core CRM entities:
 * - crm_companies: Company records
 * - crm_contacts: Contact records
 * - crm_contact_phones: Contact phone numbers with temporal validity
 * - crm_company_contacts: Company-Contact pivot
 * - crm_tags: Tags for categorization
 * - crm_products: Product catalog
 * - crm_reason_losses: Loss reasons for negotiations
 * - crm_departments: Department structure
 * - Tag pivot tables
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Enable pg_trgm for full-text search when available.
        $pgTrgmEnabled = false;
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
                $pgTrgmEnabled = true;
            } catch (\Throwable) {
                $pgTrgmEnabled = false;
            }
        }

        // =====================================================================
        // CRM_COMPANIES
        // =====================================================================
        Schema::create('crm_companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('document', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'document']);
        });

        // =====================================================================
        // CRM_CONTACTS
        // =====================================================================
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_company_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('document', 32)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('position')->nullable();
            $table->boolean('is_active')->default(true);
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

            $table->index(['tenant_id', 'crm_company_id']);
            $table->index(['tenant_id', 'document']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'whatsapp']);
            $table->unique(['tenant_id', 'email']);
        });

        // GIN indexes for full-text search (fallback to BTREE when pg_trgm is unavailable)
        if (! $pgTrgmEnabled) {
            DB::statement('CREATE INDEX crm_contacts_name_idx ON crm_contacts (name)');
            DB::statement('CREATE INDEX crm_contacts_email_idx ON crm_contacts (email)');
        } else {
            try {
                DB::statement('CREATE INDEX crm_contacts_name_trgm ON crm_contacts USING gin (name gin_trgm_ops)');
                DB::statement('CREATE INDEX crm_contacts_email_trgm ON crm_contacts USING gin (email gin_trgm_ops)');
            } catch (\Throwable) {
                DB::statement('CREATE INDEX crm_contacts_name_idx ON crm_contacts (name)');
                DB::statement('CREATE INDEX crm_contacts_email_idx ON crm_contacts (email)');
            }
        }

        // =====================================================================
        // CRM_CONTACT_PHONES - Temporal phone numbers
        // =====================================================================
        Schema::create('crm_contact_phones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_contact_id');
            $table->string('label')->nullable();
            $table->string('phone_e164', 20);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'crm_contact_id']);
        });

        // Partial unique index for active phone numbers
        DB::statement('
            CREATE UNIQUE INDEX crm_contact_phones_active_unique
            ON crm_contact_phones (tenant_id, phone_e164)
            WHERE valid_to IS NULL
        ');

        // =====================================================================
        // CRM_COMPANY_CONTACTS - Pivot table
        // =====================================================================
        Schema::create('crm_company_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_company_id');
            $table->uuid('crm_contact_id');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_company_id')
                ->references('id')
                ->on('crm_companies')
                ->cascadeOnDelete();

            $table->foreign('crm_contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'crm_company_id', 'crm_contact_id'], 'crm_company_contacts_unique');
        });

        // =====================================================================
        // CRM_TAGS
        // =====================================================================
        Schema::create('crm_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('color', 7)->nullable(); // #RRGGBB
            $table->string('category', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'name']);
        });

        // =====================================================================
        // CRM_PRODUCTS
        // =====================================================================
        Schema::create('crm_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('product');
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('track_stock')->default(false);
            $table->integer('stock')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'name']);
        });

        // Check constraint for positive price
        DB::statement('ALTER TABLE crm_products ADD CONSTRAINT chk_crm_products_price_positive CHECK (price >= 0)');

        // =====================================================================
        // CRM_REASON_LOSSES
        // =====================================================================
        Schema::create('crm_reason_losses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('requires_comment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->integer('usage_count')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
        });

        // =====================================================================
        // CRM_DEPARTMENTS
        // =====================================================================
        Schema::create('crm_departments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'name']);
        });

        // =====================================================================
        // TAG PIVOT TABLES
        // =====================================================================

        // CRM_CONTACT_TAGS
        Schema::create('crm_contact_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->nullable();
            $table->uuid('crm_contact_id');
            $table->uuid('crm_tag_id');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->cascadeOnDelete();

            $table->foreign('crm_tag_id')
                ->references('id')
                ->on('crm_tags')
                ->cascadeOnDelete();

            $table->unique(['crm_contact_id', 'crm_tag_id']);
        });

        // CRM_COMPANY_TAGS
        Schema::create('crm_company_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_company_id');
            $table->uuid('crm_tag_id');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_company_id')
                ->references('id')
                ->on('crm_companies')
                ->cascadeOnDelete();

            $table->foreign('crm_tag_id')
                ->references('id')
                ->on('crm_tags')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'crm_company_id', 'crm_tag_id']);
        });
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
