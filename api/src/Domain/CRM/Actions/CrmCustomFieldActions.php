<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMCustomFieldDTO;
use Domain\CRM\Models\CRMCustomField;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Casos de uso para campos personalizados CRM.
 */
final class CRMCustomFieldActions
{
    public function list(string $tenantId): LengthAwarePaginator
    {
        return CRMCustomField::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->paginate();
    }

    public function create(string $tenantId, CRMCustomFieldDTO $dto): CRMCustomField
    {
        $this->guardUnique($tenantId, $dto->name, $dto->entity);

        return CRMCustomField::query()->create([
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    public function update(string $tenantId, string $id, CRMCustomFieldDTO $dto): CRMCustomField
    {
        $field = $this->find($tenantId, $id);
        if ($field->name !== $dto->name || $field->entity !== $dto->entity) {
            $this->guardUnique($tenantId, $dto->name, $dto->entity);
        }

        $field->fill($dto->toArray());
        $field->save();

        return $field;
    }

    public function delete(string $tenantId, string $id): void
    {
        $field = $this->find($tenantId, $id);
        $field->delete();
    }

    public function find(string $tenantId, string $id): CRMCustomField
    {
        return CRMCustomField::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    private function guardUnique(string $tenantId, string $name, string $entity): void
    {
        $exists = CRMCustomField::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $name)
            ->where('entity', $entity)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['Campo já existe para esta entidade.'],
            ]);
        }
    }
}
