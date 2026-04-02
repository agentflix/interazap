<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Domain Extended Tables - Consolidated Migration
 *
 * Creates extended CRM entities:
 * - crm_proposals: Sales proposals
 * - crm_proposal_items: Proposal line items
 * - crm_custom_fields: Custom field definitions
 * - crm_custom_field_values: Custom field values
 * - crm_notes: Polymorphic notes
 * - crm_negotiation_files: File attachments
 * - crm_events: Calendar events
 * - crm_event_links: Event-entity links
 * - crm_event_participants: Event participants
 * - crm_event_reminders: Event reminders
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // CRM_PROPOSALS
        // =====================================================================
        Schema::create('crm_proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_negotiation_id');
            $table->string('title');
            $table->integer('number')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft, sent, viewed, accepted, rejected
            $table->date('valid_until')->nullable();
            $table->string('public_token')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_negotiation_id')
                ->references('id')
                ->on('crm_negotiations')
                ->cascadeOnDelete();
        });

        // =====================================================================
        // CRM_PROPOSAL_ITEMS
        // =====================================================================
        Schema::create('crm_proposal_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_proposal_id');
            $table->uuid('crm_product_id')->nullable();
            $table->string('name');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_proposal_id')
                ->references('id')
                ->on('crm_proposals')
                ->cascadeOnDelete();

            $table->foreign('crm_product_id')
                ->references('id')
                ->on('crm_products')
                ->nullOnDelete();
        });

        // =====================================================================
        // CRM_CUSTOM_FIELDS
        // =====================================================================
        Schema::create('crm_custom_fields', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('type', 50); // text, number, date, select, etc.
            $table->string('entity', 50)->default('contact'); // contact, company, negotiation
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'name', 'entity']);
        });

        // =====================================================================
        // CRM_CUSTOM_FIELD_VALUES
        // =====================================================================
        Schema::create('crm_custom_field_values', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_custom_field_id');
            $table->string('entity_type');
            $table->uuid('entity_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_custom_field_id')
                ->references('id')
                ->on('crm_custom_fields')
                ->cascadeOnDelete();

            $table->unique(
                ['tenant_id', 'crm_custom_field_id', 'entity_type', 'entity_id'],
                'crm_custom_values_unique'
            );
            $table->index(['entity_type', 'entity_id']);
            $table->index('crm_custom_field_id');
        });

        // =====================================================================
        // CRM_NOTES - Polymorphic notes (auth_user_id nullable for AI notes)
        // =====================================================================
        Schema::create('crm_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('entity_type');
            $table->uuid('entity_id');
            $table->uuid('auth_user_id')->nullable(); // Nullable for AI-generated notes
            $table->text('content');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('auth_user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            $table->index(['entity_type', 'entity_id']);
        });

        // =====================================================================
        // CRM_NEGOTIATION_FILES
        // =====================================================================
        Schema::create('crm_negotiation_files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_negotiation_id');
            $table->uuid('auth_user_id')->nullable();
            $table->string('name');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime_type', 100)->nullable();
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

            $table->index(['tenant_id', 'crm_negotiation_id']);
        });

        // =====================================================================
        // CRM_EVENTS - Calendar events
        // =====================================================================
        Schema::create('crm_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('auth_user_id')->nullable();
            $table->string('title');
            $table->string('type', 50)->default('meeting'); // meeting, call, task, reminder
            $table->string('status', 20)->default('scheduled'); // scheduled, in_progress, completed, cancelled
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('recurrence', 20)->default('none'); // none, daily, weekly, monthly
            $table->timestamp('recurrence_ends_at')->nullable();
            $table->string('color', 7)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('auth_user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            $table->index(['tenant_id', 'starts_at']);
            $table->index(['tenant_id', 'status']);
        });

        // =====================================================================
        // CRM_EVENT_LINKS - Polymorphic links to entities
        // =====================================================================
        Schema::create('crm_event_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_event_id');
            $table->string('linkable_type');
            $table->uuid('linkable_id');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_event_id')
                ->references('id')
                ->on('crm_events')
                ->cascadeOnDelete();

            $table->index(['linkable_type', 'linkable_id']);
        });

        // =====================================================================
        // CRM_EVENT_PARTICIPANTS
        // =====================================================================
        Schema::create('crm_event_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_event_id');
            $table->uuid('auth_user_id')->nullable();
            $table->uuid('crm_contact_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('pending'); // pending, accepted, declined, tentative
            $table->boolean('is_organizer')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_event_id')
                ->references('id')
                ->on('crm_events')
                ->cascadeOnDelete();

            $table->foreign('auth_user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            $table->foreign('crm_contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->nullOnDelete();
        });

        // =====================================================================
        // CRM_EVENT_REMINDERS
        // =====================================================================
        Schema::create('crm_event_reminders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('crm_event_id');
            $table->uuid('auth_user_id')->nullable();
            $table->string('type', 20)->default('notification'); // notification, email, sms
            $table->integer('minutes_before')->default(0);
            $table->boolean('notify_ui')->default(true);
            $table->boolean('notify_email')->default(false);
            $table->boolean('notify_push')->default(false);
            $table->boolean('notify_whatsapp')->default(false);
            $table->boolean('notify_webhook')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('crm_event_id')
                ->references('id')
                ->on('crm_events')
                ->cascadeOnDelete();

            $table->foreign('auth_user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();
        });
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
