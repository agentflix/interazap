<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para validação de senha admin no purge de tenant.
 */
final class PurgeTenantRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:1'],
        ];
    }
}
