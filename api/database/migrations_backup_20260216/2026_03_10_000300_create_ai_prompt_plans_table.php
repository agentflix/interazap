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
        Schema::create('ai_prompt_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('plan_id')->unique();
            $table->text('content');
            $table->jsonb('mandatory_rules')->nullable();
            $table->integer('token_limit_monthly')->nullable();
            $table->boolean('allow_overage')->default(false);
            $table->decimal('overage_price_per_1k', 10, 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('plan_id')
                ->references('id')
                ->on('platform_plans')
                ->cascadeOnDelete();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_plans');
    }
};
