<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

/**
 * DTO de preferências do usuário autenticado.
 *
 * Representa a estrutura completa de preferências do usuário,
 * incluindo aparência, comportamento, padrões de CRM, segurança
 * e acessibilidade.
 *
 * @readonly
 */
final readonly class UserPreferenceDTO
{
    /**
     * @param  array{theme: string, density: string, fontSize: string}  $appearance
     * @param  array{sound: bool, chatNotify: bool, quickReply: bool, confirmBulk: bool, ticketOpenMode: string}  $behavior
     * @param  array{negotiationType: string, taskStatus: string, pipelineView: string, negotiationOrder: string}  $crmDefaults
     * @param  array{sessionTimeout: int|null}  $security
     * @param  array{highContrast: bool, reducedMotion: bool}  $accessibility
     */
    public function __construct(
        public array $appearance,
        public array $behavior,
        public array $crmDefaults,
        public array $security,
        public array $accessibility,
    ) {}

    /**
     * Criar DTO a partir de array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            appearance: [
                'theme' => (string) ($data['appearance']['theme'] ?? 'system'),
                'density' => (string) ($data['appearance']['density'] ?? 'normal'),
                'fontSize' => (string) ($data['appearance']['fontSize'] ?? 'medium'),
            ],
            behavior: [
                'sound' => (bool) ($data['behavior']['sound'] ?? true),
                'chatNotify' => (bool) ($data['behavior']['chatNotify'] ?? true),
                'quickReply' => (bool) ($data['behavior']['quickReply'] ?? false),
                'confirmBulk' => (bool) ($data['behavior']['confirmBulk'] ?? true),
                'ticketOpenMode' => (string) ($data['behavior']['ticketOpenMode'] ?? 'modal'),
            ],
            crmDefaults: [
                'negotiationType' => (string) ($data['crmDefaults']['negotiationType'] ?? 'basic'),
                'taskStatus' => (string) ($data['crmDefaults']['taskStatus'] ?? 'pending'),
                'pipelineView' => (string) ($data['crmDefaults']['pipelineView'] ?? 'kanban'),
                'negotiationOrder' => (string) ($data['crmDefaults']['negotiationOrder'] ?? 'date'),
            ],
            security: [
                'sessionTimeout' => isset($data['security']['sessionTimeout'])
                    ? (int) $data['security']['sessionTimeout']
                    : 60,
            ],
            accessibility: [
                'highContrast' => (bool) ($data['accessibility']['highContrast'] ?? false),
                'reducedMotion' => (bool) ($data['accessibility']['reducedMotion'] ?? false),
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
            'appearance' => $this->appearance,
            'behavior' => $this->behavior,
            'crmDefaults' => $this->crmDefaults,
            'security' => $this->security,
            'accessibility' => $this->accessibility,
        ];
    }
}
