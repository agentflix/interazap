<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para conectar instância Uazapi.
 */
final class PlatformUazapiConnectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['nullable', 'string', 'in:qr,pair'],
            'phone' => ['nullable', 'string', 'regex:/^\\d{10,15}$/'],
        ];
    }
}
