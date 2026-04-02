<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('stream_id')->unique();
            $table->string('idempotency_key')->unique();
            $table->string('provider');
            $table->string('instance_webhook_token');
            $table->string('event_type')->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);

            $table->index(['provider', 'instance_webhook_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
    }
};
