<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload for global search endpoint.
 */
final class GlobalSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('sanctum') !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:contacts,companies,negotiations,tickets,users'],
            'per_type' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
