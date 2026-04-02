<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\DTOs\UserPreferenceDTO;
use Domain\Auth\Models\AuthUser;

/**
 * Caso de uso para obter as preferências do usuário autenticado.
 *
 * Realiza merge entre os valores armazenados no banco e os defaults,
 * garantindo que nunca retorne null — sempre um objeto completo.
 */
final class GetUserPreferencesAction
{
    private const DEFAULTS = [
        'appearance' => [
            'theme' => 'system',
            'density' => 'normal',
            'fontSize' => 'medium',
        ],
        'behavior' => [
            'sound' => true,
            'chatNotify' => true,
            'quickReply' => false,
            'confirmBulk' => true,
            'ticketOpenMode' => 'modal',
        ],
        'crmDefaults' => [
            'negotiationType' => 'basic',
            'taskStatus' => 'pending',
            'pipelineView' => 'kanban',
            'negotiationOrder' => 'date',
        ],
        'security' => [
            'sessionTimeout' => 60,
        ],
        'accessibility' => [
            'highContrast' => false,
            'reducedMotion' => false,
        ],
    ];

    /**
     * Executar a ação de obter preferências do usuário.
     *
     * Faz merge profundo entre os valores armazenados e os defaults.
     *
     * @return UserPreferenceDTO Preferências completas do usuário.
     */
    public function execute(AuthUser $user): UserPreferenceDTO
    {
        $stored = $user->preferences ?? [];

        $merged = $this->deepMerge(self::DEFAULTS, $stored);

        return UserPreferenceDTO::fromArray($merged);
    }

    /**
     * Merge profundo de arrays.
     *
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
