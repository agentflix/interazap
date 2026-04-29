<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para registro de token de dispositivo.
 */
final class RegisterDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('sanctum') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'in:ios,android,web'],
            'token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
