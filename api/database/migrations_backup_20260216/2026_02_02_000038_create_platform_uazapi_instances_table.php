<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_uazapi_instances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('system_name')->nullable();
            $table->string('token')->unique();
            $table->string('status')->default('disconnected');
            $table->string('webhook_url')->nullable();
            $table->string('admin_field_01')->nullable();
            $table->string('admin_field_02')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_status_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_uazapi_instances');
    }
};
