<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for the check-and-increment usage endpoint.
 */
final class BillingUsageCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid'],
            'channel' => ['required', 'string', 'max:20'],
            'ai_turn_id' => ['required', 'uuid'],
        ];
    }
}
