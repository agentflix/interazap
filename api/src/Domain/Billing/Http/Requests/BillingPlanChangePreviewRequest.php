<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para preview de troca de plano.
 */
final class BillingPlanChangePreviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if (! $user->can('billing.view')) {
            return false;
        }

        if (! $user->can('billing.plan.manage')) {
            return false;
        }

        return $user->isSuperAdmin() || $user->hasRole('admin', 'sanctum');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'uuid', 'exists:platform_plans,id'],
        ];
    }
}
