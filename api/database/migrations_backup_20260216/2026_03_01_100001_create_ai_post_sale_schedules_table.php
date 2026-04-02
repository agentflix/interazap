<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela para agendamentos de pós-venda automáticos.
 * Suporta D+1, D+7, D+30 para follow-up de clientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_post_sale_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('negotiation_id')->constrained('crm_negotiations')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->nullable()->constrained('chat_tickets')->nullOnDelete();

            // Schedule configuration
            $table->string('schedule_type', 20); // AiPostSaleScheduleType enum
            $table->timestamp('sale_date');
            $table->timestamp('scheduled_at');

            // Status tracking
            $table->string('status', 20)->default('pending'); // AiPostSaleStatus enum
            $table->timestamp('sent_at')->nullable();
            $table->string('message_id')->nullable(); // External message ID

            // Error handling
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            // Message customization
            $table->text('custom_message')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            // Indexes for queue processing
            $table->index(['tenant_id', 'status', 'scheduled_at']);
            $table->index(['negotiation_id', 'schedule_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_post_sale_schedules');
    }
};
