<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;

/**
 * Performance seed for Configuration context.
 *
 * Seeds notification preferences, notifications, webhooks, and push subscriptions.
 * Total: ~3,650 records across 50 tenants.
 */
final class PerformanceConfigurationSeeder
{
    use WithoutModelEvents;

    private const int BATCH_SIZE = 500;

    public function seedForTenant(string $tenantId): void
    {
        $userIds = DB::table('auth_users')->where('tenant_id', $tenantId)->pluck('id')->toArray();

        $this->seedNotificationPreferences($tenantId, $userIds);
        $this->seedNotifications($tenantId, $userIds);
        $this->seedNotificationWebhooks($tenantId);
        $this->seedPushSubscriptions($tenantId, $userIds);
    }

    private function seedNotificationPreferences(string $tenantId, array $userIds): void
    {
        $types = ['ticket_assigned', 'message_received', 'negotiation_updated', 'event_reminder', 'billing_due'];
        $channels = [['ui'], ['ui', 'email'], ['ui', 'push'], ['ui', 'email', 'push']];
        $prefs = [];

        foreach ($userIds as $userId) {
            $prefCount = random_int(2, 5);
            for ($p = 0; $p < $prefCount; $p++) {
                $hasQuietHours = (bool) random_int(0, 1);

                $prefs[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'notification_type' => $types[$p % count($types)],
                    'channels' => json_encode($channels[array_rand($channels)]),
                    'enabled' => random_int(0, 100) > 10,
                    'quiet_start' => $hasQuietHours ? sprintf('%02d:00', random_int(18, 22)) : null,
                    'quiet_end' => $hasQuietHours ? sprintf('%02d:00', random_int(6, 9)) : null,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        PerformanceSeeder::insertBatch('configuration_notification_preferences', $prefs, self::BATCH_SIZE);
    }

    private function seedNotifications(string $tenantId, array $userIds): void
    {
        $statusWeights = ['pending' => 20, 'sent' => 50, 'read' => 20, 'failed' => 10];
        $channels = ['database' => 50, 'email' => 30, 'push' => 15, 'sms' => 5];
        $types = ['ticket_assigned', 'message_received', 'negotiation_updated', 'event_reminder', 'billing_due', 'system_alert'];
        $notifications = [];
        $count = random_int(30, 70);

        for ($i = 0; $i < $count; $i++) {
            $status = PerformanceSeeder::weightedRandom($statusWeights);
            $channel = PerformanceSeeder::weightedRandom($channels);
            $sentAt = in_array($status, ['sent', 'read']) ? PerformanceSeeder::randomDate() : null;

            $notifications[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => ! empty($userIds) ? $userIds[array_rand($userIds)] : null,
                'type' => $types[array_rand($types)],
                'title' => 'Notificacao '.random_int(100, 999),
                'body' => fake('pt_BR')->sentence(),
                'data' => json_encode(['ref_id' => PerformanceSeeder::uuid()]),
                'channel' => $channel,
                'status' => $status,
                'sent_at' => $sentAt,
                'read_at' => $status === 'read' ? $sentAt->copy()->addMinutes(random_int(1, 60)) : null,
                'error_message' => $status === 'failed' ? 'Falha no envio da notificacao' : null,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('configuration_notifications', $notifications, self::BATCH_SIZE);
    }

    private function seedNotificationWebhooks(string $tenantId): void
    {
        $eventTypes = ['ticket.created', 'ticket.updated', 'message.received', 'negotiation.won', 'invoice.paid'];
        $webhooks = [];
        $count = random_int(2, 4);

        for ($i = 0; $i < $count; $i++) {
            $hasFailures = random_int(0, 100) > 70;

            $webhooks[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Webhook '.($i + 1),
                'url' => 'https://webhook.perf.local/endpoint/'.random_int(1000, 9999),
                'secret' => base64_encode(random_bytes(32)),
                'event_types' => json_encode(array_slice($eventTypes, 0, random_int(1, count($eventTypes)))),
                'is_active' => random_int(0, 100) > 10,
                'failure_count' => $hasFailures ? random_int(1, 10) : 0,
                'last_failure_at' => $hasFailures ? now()->subDays(random_int(1, 30)) : null,
                'last_success_at' => $hasFailures ? now()->subDays(random_int(1, 7)) : now()->subDays(random_int(1, 30)),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('configuration_notification_webhooks', $webhooks, self::BATCH_SIZE);
    }

    private function seedPushSubscriptions(string $tenantId, array $userIds): void
    {
        $subscriptions = [];
        $count = min(count($userIds), 5);

        for ($i = 0; $i < $count; $i++) {
            $subscriptions[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => $userIds[array_rand($userIds)],
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.base64_encode(random_bytes(64)),
                'p256dh' => base64_encode(random_bytes(32)),
                'auth' => base64_encode(random_bytes(16)),
                'content_encoding' => 'aes128gcm',
                'is_active' => random_int(0, 100) > 10,
                'last_seen_at' => PerformanceSeeder::randomDate(),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('configuration_push_subscriptions', $subscriptions, self::BATCH_SIZE);
    }
}
