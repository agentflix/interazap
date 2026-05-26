<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para empresa do CRM.
 *
 * @readonly
 */
final readonly class CRMCompanyDTO
{
    public function __construct(
        public string $name,
        public ?string $document = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $zip_code = null,
        public bool $is_active = true,
    ) {}

    /** Cria DTO a partir de uma request HTTP. */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: (string) $request->input('name'),
            document: $request->input('document'),
            email: $request->input('email'),
            phone: $request->input('phone'),
            address: $request->input('address'),
            city: $request->input('city'),
            state: $request->input('state'),
            zip_code: $request->input('zip_code'),
            is_active: (bool) $request->input('is_active', true),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            document: $data['document'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zip_code: $data['zip_code'] ?? null,
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
            'document' => $this->document,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'is_active' => $this->is_active,
        ];
    }
}
