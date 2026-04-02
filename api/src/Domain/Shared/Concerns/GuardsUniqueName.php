<?php

declare(strict_types=1);

namespace Domain\Shared\Concerns;

use Illuminate\Validation\ValidationException;

trait GuardsUniqueName
{
    /**
     * Ensure name is unique for the tenant.
     *
     * @param  class-string  $modelClass
     *
     * @throws ValidationException
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
