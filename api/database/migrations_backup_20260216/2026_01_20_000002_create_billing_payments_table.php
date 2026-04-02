<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration para criar a tabela de pagamentos do módulo Billing.
 */
return new class extends Migration
{
    public function up(): void
    {
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

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->onDelete('cascade');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('billing_invoices')
                ->onDelete('cascade');

            // Indexes
            $table->index(['tenant_id', 'created_at']);
            $table->index(['invoice_id']);
            $table->unique(['provider', 'provider_payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
    }
};
