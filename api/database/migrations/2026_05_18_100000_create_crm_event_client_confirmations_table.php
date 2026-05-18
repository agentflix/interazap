<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crm_event_client_confirmations', static function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador unico da confirmacao');
            $table->uuid('tenant_id')->comment('Tenant proprietario');
            $table->uuid('crm_event_id')->comment('Evento CRM vinculado');
            $table->uuid('crm_contact_id')->nullable()->comment('Contato cliente vinculado');
            $table->uuid('chat_ticket_id')->nullable()->comment('Ticket de chat vinculado');
            $table->string('status', 20)->default('pending')->comment('Status da confirmacao: pending, confirmed, declined');
            $table->unsignedInteger('minutes_before')->default(1440)->comment('Minutos de antecedencia para o lembrete');
            $table->timestamp('reminder_sent_at')->nullable()->comment('Quando o lembrete foi enviado');
            $table->timestamp('response_at')->nullable()->comment('Quando o cliente respondeu');
            $table->timestamp('no_response_notified_at')->nullable()->comment('Quando o tenant foi notificado de nao resposta');
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'idx_crm_event_client_confirmations_tenant_status');
            $table->index('crm_event_id', 'idx_crm_event_client_confirmations_crm_event_id');
            $table->index('chat_ticket_id', 'idx_crm_event_client_confirmations_chat_ticket_id');

            $table->foreign('tenant_id', 'fk_crm_event_client_confirmations_tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->onDelete('cascade');

            $table->foreign('crm_event_id', 'fk_crm_event_client_confirmations_crm_event_id')
                ->references('id')
                ->on('crm_events')
                ->onDelete('cascade');

            $table->foreign('crm_contact_id', 'fk_crm_event_client_confirmations_crm_contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->onDelete('set null');

            $table->foreign('chat_ticket_id', 'fk_crm_event_client_confirmations_chat_ticket_id')
                ->references('id')
                ->on('chat_tickets')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_event_client_confirmations');
    }
};
