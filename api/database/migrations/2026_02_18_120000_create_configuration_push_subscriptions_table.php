<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_push_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('endpoint', 1000);
            $table->string('p256dh');
            $table->string('auth');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('auth_users')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'user_id', 'endpoint'], 'cfg_push_subscriptions_unique');
            $table->index(['tenant_id', 'user_id', 'is_active'], 'cfg_push_subscriptions_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_push_subscriptions');
    }
};
