<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela para notificações de vendedores pela IA.
 * Suporta email e WhatsApp com fallback automático.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_seller_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('seller_id')->constrained('auth_users')->cascadeOnDelete();

            // Optional reference to the trigger (ticket, negotiation, etc.)
            $table->nullableUuidMorphs('notifiable');

            // Notification content
            $table->text('message');
            $table->string('reason', 50); // AiNotificationReason enum
            $table->string('channel', 20)->default('email'); // AiNotificationChannel enum
            $table->string('priority', 20)->default('normal'); // low, normal, high, urgent

            // Delivery tracking
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();

            // Metadata
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Indexes for queue processing
            $table->index(['tenant_id', 'delivered_at', 'failed_at']);
            $table->index(['seller_id', 'created_at']);
            $table->index(['priority', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_seller_notifications');
    }
};
