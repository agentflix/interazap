<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;

/**
 * Performance seed for Shared context (audit logs and webhook events).
 *
 * Total: ~7,500 records across 50 tenants.
 */
final class PerformanceSharedSeeder
{
    use WithoutModelEvents;

    private const int BATCH_SIZE = 1000;

    public function seedForTenant(string $tenantId): void
    {
        $userIds = DB::table('auth_users')->where('tenant_id', $tenantId)->pluck('id')->toArray();

        $this->seedAuditLogs($tenantId, $userIds);
        $this->seedWebhookEvents($tenantId);
    }

    private function seedAuditLogs(string $tenantId, array $userIds): void
    {
        $events = ['created' => 30, 'updated' => 50, 'deleted' => 20];
        $auditableTypes = [
            'Domain\\CRM\\Models\\CRMContact' => 30,
            'Domain\\CRM\\Models\\CRMCompany' => 20,
            'Domain\\CRM\\Models\\CRMNegotiation' => 25,
            'Domain\\Chat\\Models\\ChatTicket' => 15,
            'Domain\\Auth\\Models\\AuthUser' => 10,
        ];
        $logs = [];
        $count = random_int(80, 120);

        for ($i = 0; $i < $count; $i++) {
            $event = PerformanceSeeder::weightedRandom($events);
            $auditableType = PerformanceSeeder::weightedRandom($auditableTypes);

            $logs[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => ! empty($userIds) ? $userIds[array_rand($userIds)] : null,
                'user_type' => 'Domain\\Auth\\Models\\AuthUser',
                'event' => $event,
                'auditable_type' => $auditableType,
                'auditable_id' => PerformanceSeeder::uuid(),
                'old_values' => $event !== 'created' ? json_encode(['field' => 'old_value']) : null,
                'new_values' => $event !== 'deleted' ? json_encode(['field' => 'new_value']) : null,
                'url' => 'https://api.perf.local/v1/'.strtolower(str_replace('\\', '/', $auditableType)),
                'ip_address' => fake('pt_BR')->ipv4(),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'tags' => $event,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('audit_logs', $logs, self::BATCH_SIZE);
    }

    private function seedWebhookEvents(string $tenantId): void
    {
        $domains = ['chat' => 70, 'billing' => 30];
        $providers = ['uazapi' => 60, 'meta' => 25, 'asaas' => 15];
        $eventTypes = ['message.received', 'message.sent', 'status.update', 'ticket.created', 'payment.confirmed', 'invoice.generated'];
        $events = [];
        $count = random_int(40, 60);

        for ($i = 0; $i < $count; $i++) {
            $domain = PerformanceSeeder::weightedRandom($domains);
            $provider = PerformanceSeeder::weightedRandom($providers);
            $isProcessed = random_int(0, 100) > 10;

            $events[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'domain' => $domain,
                'stream_id' => (string) \Illuminate\Support\Str::uuid(),
                'idempotency_key' => hash('sha256', random_bytes(32)),
                'provider' => $provider,
                'instance_webhook_token' => (string) \Illuminate\Support\Str::uuid(),
                'provider_event_id' => 'evt_'.random_int(100000, 999999),
                'event_type' => $eventTypes[array_rand($eventTypes)],
                'direction' => $domain === 'chat' ? ['inbound', 'outbound'][array_rand(['inbound', 'outbound'])] : null,
                'payload' => json_encode(['data' => 'value']),
                'payload_hash' => hash('sha256', json_encode(['data' => 'value'])),
                'payload_json' => json_encode(['data' => 'value']),
                'received_at' => PerformanceSeeder::randomDate(),
                'processed_at' => $isProcessed ? now()->subDays(random_int(1, 30)) : null,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('shared_webhook_events', $events, self::BATCH_SIZE);
    }
}
