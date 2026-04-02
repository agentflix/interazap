<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 50)->unique();
            $table->unsignedInteger('limit_users');
            $table->string('storage_mode', 20);
            $table->unsignedBigInteger('storage_limit_bytes')->nullable();
            $table->boolean('ai_enabled');
            $table->unsignedInteger('whatsapp_integrations_limit');
            $table->string('negotiations_mode', 20);
            $table->unsignedInteger('negotiations_limit')->nullable();
            $table->decimal('price_monthly', 10, 2);
            $table->string('asaas_product_id', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_plans');
    }
};
