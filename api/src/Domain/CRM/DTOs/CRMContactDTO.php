<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for CRM contact.
 *
 * @readonly
 */
final readonly class CRMContactDTO
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $document = null,
        public ?string $phone = null,
        public ?string $position = null,
        public ?string $notes = null,
        /**
         * @var array<string, mixed>|null
         */
        public ?array $custom_fields = null,
        public ?string $crm_company_id = null,
        public ?string $phone_e164 = null,
        public bool $is_active = true,
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();

        return new self(
            name: $data['name'],
            email: $data['email'] ?? null,
            document: $data['document'] ?? null,
            phone: $data['phone'] ?? null,
            position: $data['position'] ?? null,
            notes: $data['notes'] ?? null,
            custom_fields: $data['custom_fields'] ?? null,
            crm_company_id: $data['crm_company_id'] ?? null,
            phone_e164: $data['phone_e164'] ?? null,
            is_active: (bool) ($data['is_active'] ?? true),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'] ?? null,
            document: $data['document'] ?? null,
            phone: $data['phone'] ?? null,
            position: $data['position'] ?? null,
            notes: $data['notes'] ?? null,
            custom_fields: $data['custom_fields'] ?? null,
            crm_company_id: $data['crm_company_id'] ?? null,
            phone_e164: $data['phone_e164'] ?? null,
            is_active: (bool) ($data['is_active'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'document' => $this->document,
            'phone' => $this->phone,
            'position' => $this->position,
            'notes' => $this->notes,
            'custom_fields' => $this->custom_fields,
            'crm_company_id' => $this->crm_company_id,
            'is_active' => $this->is_active,
        ];
    }
}
