<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Chat Instance serialization.
 *
 * @mixin \Domain\Chat\Models\ChatInstance
 */
final class ChatInstanceResource extends JsonResource
{
    /**
     * Connection statuses that indicate the instance is connected.
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
     * Transform the resource into an array.
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
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Determine if the instance is connected based on status and settings.
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
     * Remove sensitive data from settings array.
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

    private function normalizeChannelFallbackMessage(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
