<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Models\PlatformUazapiInstance;

/**
 * Updates chat instance connection state from webhook payloads.
 */
final class ChatWebhookConnectionStatusService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $tenantId, array $payload): void
    {
        $token = $this->extractInstanceToken($payload);
        logger()->debug('[ChatWebhookIngestor] updateInstanceConnectionStatus', [
            'token' => $token,
            'raw_instance' => data_get($payload, 'raw.instance'),
            'raw_status' => data_get($payload, 'raw.status'),
        ]);

        if (! $token) {
            logger()->warning('[ChatWebhookIngestor] No token found for connection event');

            return;
        }

        $instance = ChatInstance::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($token): void {
                $query->where('webhook_token', $token)
                    ->orWhereRaw("settings_json->>'token' = ?", [$token]);
            })
            ->first();

        if (! $instance) {
            logger()->warning('[ChatWebhookIngestor] Instance not found for token', ['token' => $token]);

            return;
        }

        $status = $this->normalizeConnectionStatus($payload);
        logger()->debug('[ChatWebhookIngestor] Updating instance status', [
            'instance_id' => $instance->id,
            'old_status' => $instance->status,
            'new_status' => $status,
        ]);

        $settings = $instance->settings_json ?? [];
        $settings['last_connection'] = [
            'status' => $status,
            'connected' => data_get($payload, 'raw.status.connected'),
            'logged_in' => data_get($payload, 'raw.status.loggedIn'),
            'instance' => data_get($payload, 'raw.instance'),
            'updated_at' => now()->toIso8601String(),
        ];

        $instance->status = $status;
        $instance->last_status_at = now();
        $instance->settings_json = $settings;
        $instance->save();

        $this->updatePlatformInstanceStatus($tenantId, $token, $status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractInstanceToken(array $payload): ?string
    {
        return $payload['instance_webhook_token']
            ?? data_get($payload, 'token')
            ?? data_get($payload, 'raw.token')
            ?? data_get($payload, 'raw.instance.token');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizeConnectionStatus(array $payload): string
    {
        $statusPayload = data_get($payload, 'raw.instance.status')
            ?? data_get($payload, 'raw.status')
            ?? data_get($payload, 'status');

        return $this->resolveStatusValue($statusPayload);
    }

    private function resolveStatusValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'connected' : 'disconnected';
        }

        if (is_array($value)) {
            if (isset($value['status']) && is_string($value['status'])) {
                return $value['status'];
            }

            if (isset($value['connected']) && is_bool($value['connected'])) {
                return $value['connected'] ? 'connected' : 'disconnected';
            }

            if (isset($value['loggedIn']) && is_bool($value['loggedIn'])) {
                return $value['loggedIn'] ? 'connected' : 'disconnected';
            }
        }

        return 'disconnected';
    }

    private function updatePlatformInstanceStatus(string $tenantId, string $token, string $status): void
    {
        $platformInstance = PlatformUazapiInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('token', $token)
            ->first();

        if (! $platformInstance) {
            logger()->debug('[ChatWebhookIngestor] PlatformUazapiInstance not found for token', ['token' => $token]);

            return;
        }

        logger()->debug('[ChatWebhookIngestor] Updating PlatformUazapiInstance status', [
            'instance_id' => $platformInstance->id,
            'old_status' => $platformInstance->status,
            'new_status' => $status,
        ]);

        $platformInstance->status = $status;
        $platformInstance->last_status_at = now();
        $platformInstance->save();
    }
}
