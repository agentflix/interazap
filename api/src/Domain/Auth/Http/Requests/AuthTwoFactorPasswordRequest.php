<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para operações que exigem senha ao gerir 2FA.
 */
final class AuthTwoFactorPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }
}
