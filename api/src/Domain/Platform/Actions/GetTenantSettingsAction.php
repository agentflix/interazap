<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Platform\DTOs\TenantSettingDTO;
use Domain\Platform\Models\PlatformTenant;

/**
 * Caso de uso para obter as configurações do tenant.
 */
final class GetTenantSettingsAction
{
    private const DEFAULTS = [
        'settings_localization' => [
            'timezone' => 'America/Sao_Paulo',
            'dateFormat' => 'DD/MM/YYYY',
            'timeFormat' => '24h',
            'currencyFormat' => 'BRL',
        ],
        'settings_privacy' => [
            'presence' => 'team',
            'readReceipt' => true,
            'notificationPreview' => true,
        ],
        'settings_chat' => [
            'auto_close_inactivity_enabled' => false,
            'auto_close_inactivity_minutes' => 30,
            'auto_close_inactivity_target' => 'both',
            'auto_close_inactivity_message' => 'Este atendimento foi encerrado automaticamente por inatividade. Caso precise de mais ajuda, por favor inicie um novo atendimento.',
        ],
    ];

    /**
     * Executar a ação de obter configurações do tenant.
     *
     * @return TenantSettingDTO Configurações completas do tenant.
     */
    public function execute(PlatformTenant $tenant): TenantSettingDTO
    {
        $storedLocalization = $tenant->settings_localization ?? [];
        $storedPrivacy = $tenant->settings_privacy ?? [];
        $storedChat = $tenant->settings_chat ?? [];

        $mergedLocalization = $this->deepMerge(self::DEFAULTS['settings_localization'], $storedLocalization);
        $mergedPrivacy = $this->deepMerge(self::DEFAULTS['settings_privacy'], $storedPrivacy);
        $mergedChat = $this->deepMerge(self::DEFAULTS['settings_chat'], $storedChat);

        return TenantSettingDTO::fromArray([
            'settings_localization' => $mergedLocalization,
            'settings_privacy' => $mergedPrivacy,
            'settings_chat' => $mergedChat,
        ]);
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function deepMerge(array $defaults, array $stored): array
    {
        $result = $defaults;

        foreach ($stored as $key => $value) {
            if (is_array($value) && is_array($defaults[$key] ?? null)) {
                $result[$key] = $this->deepMerge($defaults[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
