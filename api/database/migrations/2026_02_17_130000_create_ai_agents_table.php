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
        Schema::create('ai_agents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name', 100);
            $table->string('type', 30)->default('general');
            $table->string('role', 30)->default('general');
            $table->string('model_id', 50)->default('gpt-4o-mini');
            $table->text('system_prompt')->nullable();
            $table->unsignedInteger('max_tokens')->default(2048);
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->decimal('top_p', 3, 2)->default(1.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
