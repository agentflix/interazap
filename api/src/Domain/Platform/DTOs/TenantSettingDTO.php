<?php

declare(strict_types=1);

namespace Domain\Platform\DTOs;

/**
 * DTO de configurações do tenant (localização e privacidade).
 *
 * @readonly
 */
final readonly class TenantSettingDTO
{
    /**
     * @param  array{timezone: string, dateFormat: string, timeFormat: string, currencyFormat: string}  $settings_localization
     * @param  array{presence: string, readReceipt: bool, notificationPreview: bool}  $settings_privacy
     */
    public function __construct(
        public array $settings_localization,
        public array $settings_privacy,
    ) {}

    /**
     * Criar DTO a partir de array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            settings_localization: [
                'timezone' => (string) ($data['settings_localization']['timezone'] ?? 'America/Sao_Paulo'),
                'dateFormat' => (string) ($data['settings_localization']['dateFormat'] ?? 'DD/MM/YYYY'),
                'timeFormat' => (string) ($data['settings_localization']['timeFormat'] ?? '24h'),
                'currencyFormat' => (string) ($data['settings_localization']['currencyFormat'] ?? 'BRL'),
            ],
            settings_privacy: [
                'presence' => (string) ($data['settings_privacy']['presence'] ?? 'team'),
                'readReceipt' => (bool) ($data['settings_privacy']['readReceipt'] ?? true),
                'notificationPreview' => (bool) ($data['settings_privacy']['notificationPreview'] ?? true),
            ],
        );
    }

    /**
     * Criar DTO a partir de request validado.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        return self::fromArray($data);
    }

    /**
     * Converter DTO para array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'settings_localization' => $this->settings_localization,
            'settings_privacy' => $this->settings_privacy,
        ];
    }
}
