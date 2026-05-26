<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource para serialização de usuário autenticado.
 */
final class AuthUserResource extends BaseJsonResource
{
    /**
     * Transforma o resource em array de resposta.
     *
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'company_id' => $this->tenant_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'is_active' => (bool) $this->is_active,
            'company' => $this->whenLoaded('tenant', fn () => $this->tenant ? [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'document' => $this->tenant->document,
            ] : null),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->toArray()),
            'provider' => $this->provider,
            'has_password' => $this->password !== null,
            'email_verified_at' => $this->iso($this->email_verified_at),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
