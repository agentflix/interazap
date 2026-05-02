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
        Schema::create('chat_routing_agent_skills', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('queue_id');
            $table->uuid('user_id');
            $table->string('skill');
            $table->timestamps();

            $table->foreign('queue_id')
                ->references('id')
                ->on('chat_routing_queues')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('auth_users')
                ->cascadeOnDelete();

            $table->unique(['queue_id', 'user_id', 'skill'], 'idx_chat_routing_agent_skills_unique');
            $table->index(['queue_id', 'user_id'], 'idx_chat_routing_agent_skills_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_routing_agent_skills');
    }
};
