<?php

declare(strict_types=1);

namespace Domain\Shared\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Trait para garantir unicidade de nome dentro do escopo de um tenant.
 *
 * Dispara ValidationException quando o nome já existe para o tenant,
 * suportando opcional exclusão de um ID específico (para updates).
 */
trait GuardsUniqueName
{
    /**
     * Valida que o nome é único para o tenant informado.
     *
     * @param  class-string  $modelClass  Classe do modelo a verificar.
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $name  Nome a validar.
     * @param  string  $message  Mensagem de erro a exibir ao usuário.
     * @param  string|null  $ignoreId  ID do registro a ignorar na verificação (útil em updates).
     *
     * @throws ValidationException Quando o nome já existe para o tenant.
     */
    protected function guardUniqueName(
        string $modelClass,
        string $tenantId,
        string $name,
        string $message,
        ?string $ignoreId = null,
    ): void {
        $query = $modelClass::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $name);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        $exists = $query->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => [$message],
            ]);
        }
    }
}
