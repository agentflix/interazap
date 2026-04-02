<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Role serialization.
 */
final class AuthRoleResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->toArray()),
            'permissions_count' => $this->whenCounted('permissions'),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
