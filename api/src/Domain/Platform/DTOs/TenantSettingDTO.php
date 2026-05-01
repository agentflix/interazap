<?php

declare(strict_types=1);

namespace Domain\Platform\DTOs;

/**
 * DTO de configurações do tenant (localização, privacidade e chat).
 *
 * @readonly
 */
final readonly class TenantSettingDTO
{
    /**
     * @param  array{timezone: string, dateFormat: string, timeFormat: string, currencyFormat: string}  $settings_localization
     * @param  array{presence: string, readReceipt: bool, notificationPreview: bool}  $settings_privacy
     * @param  array{auto_close_inactivity_enabled: bool, auto_close_inactivity_minutes: int, auto_close_inactivity_target: string, auto_close_inactivity_message: string}  $settings_chat
     */
    public function __construct(
        public array $settings_localization,
        public array $settings_privacy,
        public array $settings_chat,
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
            settings_chat: [
                'auto_close_inactivity_enabled' => (bool) ($data['settings_chat']['auto_close_inactivity_enabled'] ?? false),
                'auto_close_inactivity_minutes' => (int) ($data['settings_chat']['auto_close_inactivity_minutes'] ?? 30),
                'auto_close_inactivity_target' => (string) ($data['settings_chat']['auto_close_inactivity_target'] ?? 'both'),
                'auto_close_inactivity_message' => (string) ($data['settings_chat']['auto_close_inactivity_message'] ?? 'Este atendimento foi encerrado automaticamente por inatividade. Caso precise de mais ajuda, por favor inicie um novo atendimento.'),
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
            'settings_chat' => $this->settings_chat,
        ];
    }
}
