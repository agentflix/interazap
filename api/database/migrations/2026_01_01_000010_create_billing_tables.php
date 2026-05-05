<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas do contexto Billing (faturas, pagamentos, cobranças, relatórios de purge).
 *
 * Tabelas criadas:
 * - billing_invoices
 * - billing_payments
 * - billing_collection_logs
 * - billing_purge_reports
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('billing_invoices')) {
            Schema::create('billing_invoices', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da fatura');
                $table->uuid('tenant_id')->comment('Tenant faturado (FK -> platform_tenants)');
                $table->uuid('plan_id')->nullable()->comment('Plano vigente na fatura (FK -> platform_plans)');
                $table->string('reference_month', 7)->comment('Mês de referência no formato YYYY-MM');
                $table->decimal('amount', 10, 2)->comment('Valor total da fatura em reais');
                $table->string('status', 20)->default('draft')->comment('Status: pending, paid, overdue, cancelled');
                $table->date('due_date')->comment('Data de vencimento');
                $table->timestamp('paid_at', 0)->nullable()->comment('Data/hora do pagamento');
                $table->string('payment_method', 20)->nullable()->comment('Método de pagamento: pix, boleto, credit_card');
                $table->string('payment_url', 500)->nullable()->comment('URL para pagamento no Asaas');
                $table->string('asaas_payment_id', 100)->nullable()->comment('ID da cobrança no Asaas');
                $table->text('pix_payload')->nullable()->comment('Payload copia-e-cola do PIX');
                $table->text('pix_qr_code_base64')->nullable()->comment('QR Code do PIX em base64');
                $table->jsonb('metadata')->nullable()->comment('Metadados adicionais da fatura');
                $table->timestamps(0);

                $table->index('tenant_id', 'idx_billing_invoices_tenant_id');
                $table->index('plan_id', 'idx_billing_invoices_plan_id');
                $table->index('status', 'idx_billing_invoices_status');
                $table->index('due_date', 'idx_billing_invoices_due_date');
                $table->index('reference_month', 'idx_billing_invoices_reference_month');

                $table->foreign('tenant_id')->references('id')->on('platform_tenants')->onDelete('cascade');
                $table->foreign('plan_id')->references('id')->on('platform_plans')->onDelete('restrict');
            });
        }

        if (!Schema::hasTable('billing_payments')) {
            Schema::create('billing_payments', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do pagamento');
                $table->uuid('tenant_id')->comment('Tenant pagador (FK -> platform_tenants)');
                $table->uuid('invoice_id')->comment('Fatura quitada (FK -> billing_invoices)');
                $table->decimal('amount', 10, 2)->comment('Valor efetivamente pago');
                $table->string('payment_method', 20)->comment('Método utilizado: pix, boleto, credit_card');
                $table->string('provider', 20)->default('asaas')->comment('Provedor do pagamento (ex: asaas)');
                $table->string('provider_payment_id', 100)->comment('ID da transação no provedor');
                $table->string('status', 20)->default('confirmed')->comment('Status: pending, confirmed, failed, refunded');
                $table->timestamp('confirmed_at', 0)->nullable()->comment('Quando o pagamento foi confirmado');
                $table->jsonb('metadata')->nullable()->comment('Metadados adicionais do pagamento');
                $table->timestamps(0);

                $table->index('tenant_id', 'idx_billing_payments_tenant_id');
                $table->index('invoice_id', 'idx_billing_payments_invoice_id');
                $table->index('status', 'idx_billing_payments_status');
                $table->index('confirmed_at', 'idx_billing_payments_confirmed_at');

                $table->foreign('tenant_id')->references('id')->on('platform_tenants')->onDelete('cascade');
                $table->foreign('invoice_id')->references('id')->on('billing_invoices')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('billing_collection_logs')) {
            Schema::create('billing_collection_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do log de cobrança');
                $table->uuid('tenant_id')->comment('Tenant cobrado (FK -> platform_tenants)');
                $table->uuid('invoice_id')->comment('Fatura vinculada (FK -> billing_invoices)');
                $table->string('template_id', 50)->comment('Template de mensagem utilizado');
                $table->string('channel', 20)->comment('Canal de envio: email, sms, whatsapp');
                $table->string('recipient', 255)->comment('Destinatário da cobrança (e-mail/telefone)');
                $table->string('status', 20)->comment('Status do envio: sent, delivered, failed');
                $table->string('provider_message_id', 100)->nullable()->comment('ID da mensagem no provedor de envio');
                $table->jsonb('metadata')->nullable()->comment('Metadados adicionais do envio');
                $table->timestamps(0);

                $table->index('tenant_id', 'idx_billing_collection_tenant_id');
                $table->index('invoice_id', 'idx_billing_collection_invoice_id');
                $table->index('status', 'idx_billing_collection_status');
                $table->index('channel', 'idx_billing_collection_channel');

                $table->foreign('tenant_id')->references('id')->on('platform_tenants')->onDelete('cascade');
                $table->foreign('invoice_id')->references('id')->on('billing_invoices')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('billing_purge_reports')) {
            Schema::create('billing_purge_reports', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do relatório de purge');
                $table->uuid('tenant_id')->comment('Tenant purgado (FK -> platform_tenants)');
                $table->string('tenant_name', 255)->comment('Nome do tenant no momento do purge');
                $table->string('tenant_document', 20)->nullable()->comment('Documento do tenant no momento do purge');
                $table->string('tenant_email', 255)->nullable()->comment('E-mail do tenant no momento do purge');
                $table->jsonb('summary')->comment('Resumo do que foi removido (quantidades, tabelas, etc.)');
                $table->jsonb('invoices_snapshot')->comment('Snapshot das faturas existentes no momento do purge');
                $table->timestamp('purged_at', 0)->comment('Data/hora em que o purge foi executado');
                $table->string('purged_by', 20)->default('system')->comment('Usuário ou sistema que executou o purge');
                $table->timestamps(0);

                $table->index('tenant_id', 'idx_billing_purge_tenant_id');
                $table->index('purged_at', 'idx_billing_purge_purged_at');

                $table->foreign('tenant_id')->references('id')->on('platform_tenants')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_purge_reports');
        Schema::dropIfExists('billing_collection_logs');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_invoices');
    }
};
