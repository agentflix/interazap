<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Platform\DTOs\TenantSettingDTO;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso para atualizar as configurações do tenant.
 *
 * Realiza deep merge — não substitui seções não enviadas.
 */
final class UpdateTenantSettingsAction
{
    /**
     * Executar a ação de atualizar configurações do tenant.
     *
     * @param  array<string, mixed>  $payload  Dados validados da request.
     * @return TenantSettingDTO Configurações atualizadas completas.
     */
    public function execute(PlatformTenant $tenant, array $payload): TenantSettingDTO
    {
        $current = DB::transaction(function () use ($tenant, $payload): array {
            $tenant->refresh();

            $settingsLocalization = $tenant->settings_localization ?? [];
            $settingsPrivacy = $tenant->settings_privacy ?? [];
            $settingsChat = $tenant->settings_chat ?? [];

            if (array_key_exists('settings_localization', $payload)) {
                $settingsLocalization = $this->deepMerge(
                    $settingsLocalization,
                    $payload['settings_localization']
                );
            }

            if (array_key_exists('settings_privacy', $payload)) {
                $settingsPrivacy = $this->deepMerge(
                    $settingsPrivacy,
                    $payload['settings_privacy']
                );
            }

            if (array_key_exists('settings_chat', $payload)) {
                $settingsChat = $this->deepMerge(
                    $settingsChat,
                    $payload['settings_chat']
                );
            }

            $tenant->update([
                'settings_localization' => $settingsLocalization,
                'settings_privacy' => $settingsPrivacy,
                'settings_chat' => $settingsChat,
            ]);

            return [
                'settings_localization' => $settingsLocalization,
                'settings_privacy' => $settingsPrivacy,
                'settings_chat' => $settingsChat,
            ];
        });

        return TenantSettingDTO::fromArray($current);
    }

    /**
     * Merge profundo de arrays.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function deepMerge(array $base, array $override): array
    {
        $result = $base;

        foreach ($override as $key => $value) {
            if (is_array($value) && is_array($result[$key] ?? null)) {
                $result[$key] = $this->deepMerge($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
