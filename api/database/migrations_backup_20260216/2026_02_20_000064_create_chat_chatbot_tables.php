<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_chatbot_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger_text');
            $table->text('response_text');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->timestamps();
        });

        Schema::create('chat_chatbot_cooldowns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('chat_tickets')->cascadeOnDelete();
            $table->foreignUuid('rule_id')->constrained('chat_chatbot_rules')->cascadeOnDelete();
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_chatbot_cooldowns');
        Schema::dropIfExists('chat_chatbot_rules');
    }
};
