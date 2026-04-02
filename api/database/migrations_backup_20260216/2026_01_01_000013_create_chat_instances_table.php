<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_instances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->string('provider');
            $table->string('name')->nullable();
            $table->string('mode')->default('production');
            $table->string('status')->default('disconnected');
            $table->boolean('is_active')->default(true);
            $table->boolean('evaluation_enabled')->default(false);
            $table->string('webhook_token')->unique();
            $table->json('settings_json')->nullable();
            $table->timestamp('last_status_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'provider']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_instances');
    }
};
