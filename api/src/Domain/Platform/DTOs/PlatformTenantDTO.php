<?php

declare(strict_types=1);

namespace Domain\Platform\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO para criação e atualização de tenants da plataforma.
 *
 * Normaliza telefone, documento, estado e CEP antes de transportar os dados ao domínio.
 *
 * @readonly
 */
final readonly class PlatformTenantDTO
{
    public function __construct(
        public string $name,
        public ?string $primaryEmail = null,
        public ?string $document = null,
        public ?string $tenantCode = null,
        public ?bool $isActive = null,
        public ?string $segmentId = null,
        public ?string $planId = null,
        public ?string $phone = null,
        public ?string $street = null,
        public ?string $number = null,
        public ?string $complement = null,
        public ?string $district = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $zipCode = null,
    ) {}

    /**
     * Cria o DTO a partir de um FormRequest validado.
     *
     * @param  FormRequest  $request  Requisição validada.
     * @return self DTO preenchido.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Cria o DTO a partir de um array de dados normalizando campos de endereço e contato.
     *
     * @param  array<string, mixed>  $data  Dados do tenant.
     * @return self DTO preenchido.
     */
    public static function fromArray(array $data): self
    {
        $state = isset($data['state']) && is_string($data['state']) ? strtoupper(trim($data['state'])) : null;

        $zipCode = $data['zip_code'] ?? $data['zip'] ?? $data['zipcode'] ?? null;
        if (is_string($zipCode)) {
            $zipCode = preg_replace('/\D+/', '', $zipCode);
        }

        $phone = $data['phone'] ?? null;
        if (is_string($phone)) {
            $phone = preg_replace('/\D+/', '', $phone);
        }

        $document = $data['document'] ?? null;
        if (is_string($document)) {
            $document = preg_replace('/\D+/', '', $document);
        }

        return new self(
            name: (string) ($data['name'] ?? ''),
            primaryEmail: isset($data['email']) ? (string) $data['email'] : null,
            document: $document,
            tenantCode: $data['tenant_code'] ?? null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
            segmentId: $data['segment_id'] ?? null,
            planId: $data['plan_id'] ?? null,
            phone: self::sanitizeNullableString($phone),
            street: self::sanitizeNullableString($data['street'] ?? $data['address'] ?? null),
            number: self::sanitizeNullableString($data['number'] ?? null),
            complement: self::sanitizeNullableString($data['complement'] ?? null),
            district: self::sanitizeNullableString($data['district'] ?? null),
            city: self::sanitizeNullableString($data['city'] ?? null),
            state: self::sanitizeNullableString($state),
            zipCode: self::sanitizeNullableString($zipCode),
        );
    }

    /**
     * Converte o DTO em array para persistência no banco.
     *
     * @return array<string, mixed> Representação em array do tenant.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'primary_email' => $this->primaryEmail,
            'document' => $this->document,
            'tenant_code' => $this->tenantCode,
            'is_active' => $this->isActive,
            'segment_id' => $this->segmentId,
            'plan_id' => $this->planId,
            'phone' => $this->phone,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'district' => $this->district,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
        ];
    }

    /**
     * Converte valor para string ou null, eliminando strings em branco.
     *
     * @param  mixed  $value  Valor a ser sanitizado.
     * @return string|null String trimada ou null se vazia.
     */
    private static function sanitizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
