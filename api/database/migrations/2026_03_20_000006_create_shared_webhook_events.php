<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-004 Fase 3c — Create shared_webhook_events
 *
 * Consolidates chat_webhook_events and billing_webhook_events into a single
 * shared_webhook_events table with a domain discriminator.
 * Unifies idempotency logic across domains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('domain', 20);
            $table->string('stream_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('provider', 50);
            $table->string('instance_webhook_token');
            $table->string('provider_event_id', 100)->nullable();
            $table->string('event_type', 100)->nullable();
            $table->string('direction', 20)->nullable();
            $table->json('payload');
            $table->string('payload_hash', 64)->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            // Domain-scoped unique indexes
            $table->unique(['domain', 'stream_id'], 'idx_shared_webhook_events_stream');
            $table->unique(['domain', 'idempotency_key'], 'idx_shared_webhook_events_idempotency');

            // Performance indexes
            $table->index(['tenant_id', 'domain', 'created_at'], 'idx_shared_webhook_tenant_domain');
            $table->index(['provider', 'instance_webhook_token'], 'idx_shared_webhook_provider_token');
            $table->index(['tenant_id', 'event_type', 'created_at'], 'idx_shared_webhook_event_type');
        });

        // Migrate chat_webhook_events
        DB::statement("
            INSERT INTO shared_webhook_events (
                id, tenant_id, domain, stream_id, idempotency_key,
                provider, instance_webhook_token, event_type, direction,
                payload, processed_at, created_at, updated_at
            )
            SELECT
                id, tenant_id, 'chat', stream_id, idempotency_key,
                provider, instance_webhook_token, event_type, direction,
                payload, processed_at, created_at, updated_at
            FROM chat_webhook_events
        ");

        // Migrate billing_webhook_events
        DB::statement("
            INSERT INTO shared_webhook_events (
                id, tenant_id, domain, provider, instance_webhook_token,
                provider_event_id, event_type, payload, payload_hash,
                payload_json, received_at, processed_at, created_at, updated_at
            )
            SELECT
                id, tenant_id, 'billing', provider, instance_webhook_token,
                provider_event_id, event_type, payload, payload_hash,
                payload_json, received_at, processed_at, created_at, updated_at
            FROM billing_webhook_events
        ");

        Schema::dropIfExists('chat_webhook_events');
        Schema::dropIfExists('billing_webhook_events');
    }

    public function down(): void
    {
        // Recreate chat_webhook_events
        Schema::create('chat_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('stream_id')->unique();
            $table->string('idempotency_key')->unique();
            $table->string('provider', 50);
            $table->string('instance_webhook_token');
            $table->string('event_type', 100)->nullable();
            $table->string('direction', 20)->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['provider', 'instance_webhook_token']);
        });

        // Recreate billing_webhook_events
        Schema::create('billing_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('provider', 50);
            $table->string('provider_event_id', 100)->nullable();
            $table->string('instance_webhook_token');
            $table->string('event_type', 100)->nullable();
            $table->json('payload');
            $table->string('payload_hash', 64)->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'provider_event_id'], 'billing_webhook_provider_event_unique');
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'event_type', 'created_at']);
            $table->index(['provider', 'instance_webhook_token']);
        });

        // Restore data
        DB::statement("
            INSERT INTO chat_webhook_events (
                id, tenant_id, stream_id, idempotency_key, provider,
                instance_webhook_token, event_type, direction, payload,
                processed_at, created_at, updated_at
            )
            SELECT
                id, tenant_id, stream_id, idempotency_key, provider,
                instance_webhook_token, event_type, direction, payload,
                processed_at, created_at, updated_at
            FROM shared_webhook_events WHERE domain = 'chat'
        ");

        DB::statement("
            INSERT INTO billing_webhook_events (
                id, tenant_id, provider, provider_event_id, instance_webhook_token,
                event_type, payload, payload_hash, payload_json,
                received_at, processed_at, created_at, updated_at
            )
            SELECT
                id, tenant_id, provider, provider_event_id, instance_webhook_token,
                event_type, payload, payload_hash, payload_json,
                received_at, processed_at, created_at, updated_at
            FROM shared_webhook_events WHERE domain = 'billing'
        ");

        Schema::dropIfExists('shared_webhook_events');
    }
};
