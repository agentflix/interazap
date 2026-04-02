<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request for list filtering payloads shared across domains.
 */
class FilterRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:100'],
            'direction' => ['sometimes', 'nullable', 'in:asc,desc'],
        ];
    }
}
