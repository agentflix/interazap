<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for CRM contact phone.
 *
 * @readonly
 */
final readonly class CRMContactPhoneDTO
{
    public function __construct(
        public string $crm_contact_id,
        public string $phone_e164,
        public ?string $label = null,
        public bool $is_primary = true,
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request, string $contactId): self
    {
        return self::fromArray($request->validated(), $contactId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $contactId = ''): self
    {
        return new self(
            crm_contact_id: $contactId ?: ($data['crm_contact_id'] ?? ''),
            phone_e164: (string) ($data['phone_e164'] ?? ''),
            label: $data['label'] ?? null,
            is_primary: (bool) ($data['is_primary'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'crm_contact_id' => $this->crm_contact_id,
            'phone_e164' => $this->phone_e164,
            'label' => $this->label,
            'is_primary' => $this->is_primary,
        ];
    }
}
