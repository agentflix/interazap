<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration para criar a tabela de faturas do módulo Billing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('plan_id')->nullable();
            $table->string('reference_month', 7); // Ex: "2026-01"
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

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->onDelete('cascade');

            if (Schema::hasTable('platform_plans')) {
                $table->foreign('plan_id')
                    ->references('id')
                    ->on('platform_plans')
                    ->onDelete('set null');
            }

            // Indexes
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'reference_month']);
            $table->unique(['tenant_id', 'reference_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
