<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_routing_queues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('instance_id')->nullable();
            $table->string('name');
            $table->boolean('is_enabled')->default(false);
            $table->enum('strategy', ['round_robin', 'least_busy', 'skill_based'])->default('round_robin');
            $table->unsignedInteger('max_open_tickets_per_agent')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('instance_id')
                ->references('id')
                ->on('chat_instances')
                ->cascadeOnDelete();

            // Standard index for channel-scoped lookups
            $table->index(['tenant_id', 'instance_id'], 'idx_chat_routing_queues_tenant_instance');
        });

        // Partial unique indexes (PostgreSQL-specific)
        DB::statement('CREATE UNIQUE INDEX idx_chat_routing_queues_global ON chat_routing_queues (tenant_id) WHERE instance_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX idx_chat_routing_queues_instance ON chat_routing_queues (tenant_id, instance_id) WHERE instance_id IS NOT NULL');

        // Partial index for global queue lookups
        DB::statement('CREATE INDEX idx_chat_routing_queues_global_lookup ON chat_routing_queues (tenant_id) WHERE instance_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_routing_queues');
    }
};
