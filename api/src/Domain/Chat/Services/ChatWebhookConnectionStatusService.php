<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Models\PlatformUazapiInstance;

/**
 * Atualiza o estado de conexão da instância de chat a partir de payloads de webhook.
 *
 * Localiza a instância pelo token, normaliza o status de conexão recebido e persiste
 * tanto em ChatInstance quanto em PlatformUazapiInstance quando aplicável.
 *
 * @category Services
 */
final class ChatWebhookConnectionStatusService
{
    /**
     * Processa evento de conexão/desconexão da instância.
     *
     * Extrai o token do payload, localiza a instância e persiste o novo status.
     * Registra warning no log se o token ou a instância não forem encontrados.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $payload  Payload bruto do webhook de conexão.
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
     * Extrai o token da instância a partir do payload de webhook de conexão.
     *
     * Tenta múltiplos caminhos: instance_webhook_token, token, raw.token, raw.instance.token.
     *
     * @param  array<string, mixed>  $payload  Payload bruto do webhook.
     * @return string|null Token da instância ou null se não encontrado.
     */
    private function extractInstanceToken(array $payload): ?string
    {
        return $payload['instance_webhook_token']
            ?? data_get($payload, 'token')
            ?? data_get($payload, 'raw.token')
            ?? data_get($payload, 'raw.instance.token');
    }

    /**
     * Normaliza o status de conexão a partir do payload bruto.
     *
     * Tenta múltiplos caminhos: raw.instance.status, raw.status, status.
     *
     * @param  array<string, mixed>  $payload  Payload bruto do webhook.
     * @return string Status normalizado (ex.: 'connected', 'disconnected').
     */
    private function normalizeConnectionStatus(array $payload): string
    {
        $statusPayload = data_get($payload, 'raw.instance.status')
            ?? data_get($payload, 'raw.status')
            ?? data_get($payload, 'status');

        return $this->resolveStatusValue($statusPayload);
    }

    /**
     * Resolve o valor de status para string normalizada.
     *
     * Converte bool (true='connected'), string direta ou array com chaves
     * status/connected/loggedIn. Padrão: 'disconnected'.
     *
     * @param  mixed  $value  Valor bruto do campo de status.
     * @return string Status resolvido.
     */
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

    /**
     * Atualiza o status da PlatformUazapiInstance correspondente ao token.
     *
     * Opera silenciosamente — registra apenas debug se a instância não for encontrada.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $token  Token da instância.
     * @param  string  $status  Novo status normalizado.
     */
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
