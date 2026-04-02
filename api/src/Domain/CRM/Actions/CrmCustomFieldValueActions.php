<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMCustomFieldValueDTO;
use Domain\CRM\Models\CRMCustomField;
use Domain\CRM\Models\CRMCustomFieldValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Casos de uso para valores de campos personalizados.
 */
final class CRMCustomFieldValueActions
{
    public function upsert(string $tenantId, CRMCustomFieldValueDTO $dto): CRMCustomFieldValue
    {
        $field = CRMCustomField::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($dto->crm_custom_field_id);

        $dto->validateValue($field);

        return DB::transaction(function () use ($tenantId, $dto): CRMCustomFieldValue {
            $value = CRMCustomFieldValue::query()
                ->where('tenant_id', $tenantId)
                ->where('crm_custom_field_id', $dto->crm_custom_field_id)
                ->where('entity_type', $dto->entity_type)
                ->where('entity_id', $dto->entity_id)
                ->first();

            if (! $value) {
                $value = new CRMCustomFieldValue([
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $tenantId,
                    'crm_custom_field_id' => $dto->crm_custom_field_id,
                    'entity_type' => $dto->entity_type,
                    'entity_id' => $dto->entity_id,
                ]);
            }

            $value->value = is_array($dto->value) ? json_encode($dto->value) : $dto->value;
            $value->save();

            return $value;
        });
    }
}
