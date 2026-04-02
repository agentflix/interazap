<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\DTOs\UserPreferenceDTO;
use Domain\Auth\Models\AuthUser;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso para atualizar as preferências do usuário autenticado.
 *
 * Realiza deep merge — não substitui seções não enviadas.
 */
final class UpdateUserPreferencesAction
{
    /**
     * Executar a ação de atualizar preferências do usuário.
     *
     * Faz deep merge entre os valores existentes no banco e os novos
     * valores do payload. Seções não enviadas são preservadas.
     *
     * @param  array<string, mixed>  $payload  Dados validados da request.
     * @return UserPreferenceDTO Preferências atualizadas completas.
     */
    public function execute(AuthUser $user, array $payload): UserPreferenceDTO
    {
        $current = DB::transaction(function () use ($user, $payload): array {
            $user->refresh();

            $existing = $user->preferences ?? [];

            $merged = $this->deepMerge($existing, $payload);

            $user->update(['preferences' => $merged]);

            return $merged;
        });

        return UserPreferenceDTO::fromArray($current);
    }

    /**
     * Merge profundo de arrays — valores null preservam o existente.
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
