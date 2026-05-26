<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Domain\CRM\Models\CRMCustomField;
use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO para valor de campo personalizado CRM.
 *
 * @readonly
 */
final readonly class CRMCustomFieldValueDTO
{
    public function __construct(
        public string $crm_custom_field_id,
        public string $entity_type,
        public string $entity_id,
        public mixed $value,
    ) {}

    /** Cria DTO a partir de um FormRequest com tipo e ID da entidade. */
    public static function fromRequest(FormRequest $request, string $entityType, string $entityId): self
    {
        $data = $request->validated();

        return new self(
            crm_custom_field_id: $data['crm_custom_field_id'],
            entity_type: $entityType,
            entity_id: $entityId,
            value: $data['value'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $entityType, string $entityId): self
    {
        return new self(
            crm_custom_field_id: $data['crm_custom_field_id'],
            entity_type: $entityType,
            entity_id: $entityId,
            value: $data['value'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'crm_custom_field_id' => $this->crm_custom_field_id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'value' => $this->value,
        ];
    }

    /**
     * Valida o valor conforme a definição do campo personalizado.
     *
     * @throws \Illuminate\Validation\ValidationException Quando o valor não é compatível com o tipo
     */
    public function validateValue(CRMCustomField $field): void
    {
        $value = $this->value;

        $type = $field->type;
        if ($type === 'number' && ! is_numeric($value)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => ['Valor deve ser numérico']]);
        }

        if ($type === 'date' && $value !== null && ! strtotime((string) $value)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => ['Data inválida']]);
        }

        if (in_array($type, ['select', 'multiselect'], true) && $field->options) {
            $options = $field->options;
            $values = $type === 'multiselect' ? (array) $value : [$value];
            foreach ($values as $v) {
                if (! in_array($v, $options, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['value' => ['Opção inválida']]);
                }
            }
        }

        if ($type === 'boolean' && ! is_null($value) && ! is_bool($value)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => ['Valor deve ser booleano']]);
        }
    }
}
