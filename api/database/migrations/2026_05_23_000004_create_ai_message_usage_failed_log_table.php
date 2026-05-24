<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_message_usage_failed_log')) {
            return;
        }

        Schema::create('ai_message_usage_failed_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('ai_turn_id', 36);
            $table->string('channel', 20);
            $table->timestamp('attempted_at', 0);
            $table->string('reason', 255)->nullable();
            $table->timestamp('reconciled_at', 0)->nullable();
            $table->timestamps(0);

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->unique('ai_turn_id', 'uq_ai_msg_failed_log_turn');
            $table->index(['tenant_id', 'attempted_at'], 'idx_ai_msg_failed_log_tenant_attempted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_message_usage_failed_log');
    }
};
