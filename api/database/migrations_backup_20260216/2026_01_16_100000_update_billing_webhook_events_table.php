<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_webhook_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('billing_webhook_events', 'provider_event_id')) {
                $table->string('provider_event_id')->nullable()->after('provider');
            }

            if (! Schema::hasColumn('billing_webhook_events', 'payload_hash')) {
                $table->string('payload_hash')->nullable()->after('payload');
            }

            if (! Schema::hasColumn('billing_webhook_events', 'payload_json')) {
                $table->json('payload_json')->nullable()->after('payload_hash');
            }

            if (! Schema::hasColumn('billing_webhook_events', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('payload_json');
            }

            // Ajustar índices para idempotência por tenant + provider_event_id
            if (Schema::hasColumn('billing_webhook_events', 'stream_id')) {
                $table->dropUnique('billing_webhook_events_stream_id_unique');
            }

            if (Schema::hasColumn('billing_webhook_events', 'idempotency_key')) {
                $table->dropUnique('billing_webhook_events_idempotency_key_unique');
            }

            $table->unique(['tenant_id', 'idempotency_key'], 'billing_webhook_events_tenant_id_idempotency_key_unique');
            $table->unique(['tenant_id', 'provider_event_id'], 'billing_webhook_events_tenant_id_provider_event_id_unique');
            $table->index(['tenant_id', 'event_type', 'created_at'], 'billing_webhook_events_tenant_event_created_idx');
        });

        // Forçar NOT NULL em tenant_id conforme contrato
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE billing_webhook_events ALTER COLUMN tenant_id SET NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('billing_webhook_events', function (Blueprint $table): void {
            $table->dropUnique('billing_webhook_events_tenant_id_idempotency_key_unique');
            $table->dropUnique('billing_webhook_events_tenant_id_provider_event_id_unique');
            $table->dropIndex('billing_webhook_events_tenant_event_created_idx');

            if (Schema::hasColumn('billing_webhook_events', 'provider_event_id')) {
                $table->dropColumn('provider_event_id');
            }

            if (Schema::hasColumn('billing_webhook_events', 'payload_hash')) {
                $table->dropColumn('payload_hash');
            }

            if (Schema::hasColumn('billing_webhook_events', 'payload_json')) {
                $table->dropColumn('payload_json');
            }

            if (Schema::hasColumn('billing_webhook_events', 'received_at')) {
                $table->dropColumn('received_at');
            }

            $table->unique('stream_id', 'billing_webhook_events_stream_id_unique');
            $table->unique('idempotency_key', 'billing_webhook_events_idempotency_key_unique');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE billing_webhook_events ALTER COLUMN tenant_id DROP NOT NULL');
        }
    }
};
