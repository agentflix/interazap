<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_message_usage')) {
            return;
        }

        Schema::create('tenant_message_usage', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->date('cycle_start');
            $table->date('cycle_end');
            $table->integer('message_count')->default(0);
            $table->integer('overage_count')->default(0);
            $table->timestamp('alert_80_sent_at', 0)->nullable();
            $table->timestamp('alert_100_sent_at', 0)->nullable();
            $table->timestamps(0);

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'cycle_start'], 'uq_tenant_msg_usage_tenant_cycle');
            $table->index(['tenant_id', 'cycle_end'], 'idx_tenant_msg_usage_tenant_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_message_usage');
    }
};
