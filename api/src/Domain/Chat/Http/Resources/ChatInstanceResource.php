<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de serialização de Instância de Chat.
 *
 * Transforma a entidade ChatInstance no formato da API, sanitizando
 * dados sensíveis das configurações (tokens, senhas) antes de expô-los.
 *
 * @mixin \Domain\Chat\Models\ChatInstance
 */
final class ChatInstanceResource extends JsonResource
{
    /**
     * Status de conexão que indicam que a instância está conectada ao provedor.
     *
     * @var array<int, string>
     */
    private const CONNECTED_STATUSES = [
        'connected',
        'online',
        'ready',
        'open',
        'authorized',
        'authenticated',
        'authenticated_connected',
        'conectado', // Portuguese variant from some providers
    ];

    /**
     * Transforma a entidade no array de resposta da API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->settings_json ?? [];
        $connectionStatus = $this->status;
        $isConnected = $this->computeIsConnected($connectionStatus, $settings);

        // Sanitize settings to remove sensitive data
        $safeSettings = $this->sanitizeSettings($settings);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'provider' => $this->provider,
            'is_active' => $this->is_active,
            'evaluation_enabled' => (bool) ($this->evaluation_enabled ?? false),
            'evaluation_cutoff_score' => (int) ($this->evaluation_cutoff_score ?? 3),
            'settings' => $safeSettings,
            'has_token' => isset($settings['token']) && $settings['token'] !== '',
            'connection_status' => $connectionStatus,
            'is_connected' => $isConnected,
            'last_status_at' => $this->last_status_at?->toIso8601String(),
            'auto_close_enabled' => $this->auto_close_enabled,
            'auto_close_after_minutes' => $this->auto_close_after_minutes,
            'auto_close_target' => $this->auto_close_target,
            'auto_close_message' => $this->auto_close_message,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Determinar se a instância está conectada com base no status e nas configurações.
     *
     * @param  array<string, mixed>  $settings
     */
    private function computeIsConnected(?string $status, array $settings): bool
    {
        // Check status string
        if ($status && in_array(strtolower($status), self::CONNECTED_STATUSES, true)) {
            return true;
        }

        // Check last_connection flags
        $lastConnection = $settings['last_connection'] ?? [];
        if (! empty($lastConnection['connected']) || ! empty($lastConnection['logged_in'])) {
            return true;
        }

        return false;
    }

    /**
     * Remover dados sensíveis do array de configurações antes de expô-los na API.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function sanitizeSettings(array $settings): array
    {
        if (array_key_exists('channel_fallback_message', $settings)) {
            $settings['channel_fallback_message'] = $this->normalizeChannelFallbackMessage(
                $settings['channel_fallback_message']
            );
        }

        $sensitiveKeys = ['token', 'password', 'secret', 'api_key', 'private_key', 'access_token', 'refresh_token'];

        foreach ($sensitiveKeys as $key) {
            unset($settings[$key]);
        }

        return $settings;
    }

    /**
     * Normalizar o valor da mensagem de fallback de canal, retornando null se vazio.
     */
    private function normalizeChannelFallbackMessage(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
