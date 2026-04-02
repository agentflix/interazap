<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing Domain Tables - Consolidated Migration
 *
 * Creates billing infrastructure:
 * - billing_webhook_events: Webhook event processing
 * - billing_invoices: Invoice management
 * - billing_payments: Payment records
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // BILLING_WEBHOOK_EVENTS - Webhook event processing with idempotency
        // =====================================================================
        Schema::create('billing_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('provider', 50);
            $table->string('provider_event_id', 100)->nullable();
            $table->string('instance_webhook_token');
            $table->string('event_type', 100)->nullable();
            $table->json('payload');
            $table->string('payload_hash', 64)->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            // Indexes for idempotency and lookups
            $table->unique(['tenant_id', 'provider_event_id'], 'billing_webhook_provider_event_unique');
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'event_type', 'created_at']);
            $table->index(['provider', 'instance_webhook_token']);
        });

        // =====================================================================
        // BILLING_INVOICES - Invoice management
        // =====================================================================
        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('plan_id')->nullable();
            $table->string('reference_month', 7); // Format: YYYY-MM
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('draft'); // draft, pending, paid, overdue, cancelled
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable(); // pix, credit_card
            $table->string('payment_url', 500)->nullable();
            $table->string('asaas_payment_id', 100)->nullable();
            $table->text('pix_payload')->nullable();
            $table->text('pix_qr_code_base64')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('plan_id')
                ->references('id')
                ->on('platform_plans')
                ->nullOnDelete();

            // Indexes
            $table->unique(['tenant_id', 'reference_month']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });

        // =====================================================================
        // BILLING_PAYMENTS - Payment records
        // =====================================================================
        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('invoice_id');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 20); // pix, credit_card
            $table->string('provider', 20)->default('asaas');
            $table->string('provider_payment_id', 100);
            $table->string('status', 20)->default('confirmed'); // confirmed, refunded
            $table->timestamp('confirmed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('billing_invoices')
                ->cascadeOnDelete();

            // Indexes
            $table->unique(['provider', 'provider_payment_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('billing_webhook_events');
    }
};
