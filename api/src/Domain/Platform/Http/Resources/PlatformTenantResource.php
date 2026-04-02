<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Platform Tenant serialization.
 */
final class PlatformTenantResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        $address = collect([$this->street, $this->number, $this->complement, $this->district])
            ->filter(fn (mixed $part): bool => is_string($part) && $part !== '')
            ->implode(', ');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'tenant_code' => $this->tenant_code,
            'primary_email' => $this->primary_email,
            'email' => $this->primary_email,
            'document' => $this->document,
            'phone' => $this->phone,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'district' => $this->district,
            'address' => $address,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip_code,
            'zip_code' => $this->zip_code,
            'zipcode' => $this->zip_code,
            'is_active' => (bool) $this->is_active,
            'segment_id' => $this->segment_id,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
            'deleted_at' => $this->iso($this->deleted_at),
        ];
    }
}
