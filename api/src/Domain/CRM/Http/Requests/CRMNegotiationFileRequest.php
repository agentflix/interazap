<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Domain\Platform\Exceptions\PlanLimitExceededException;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Domain\Shared\Models\SharedMedia;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para upload de arquivos de negociação.
 */
class CRMNegotiationFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $allowed = $user->can('create', SharedMedia::class);
        if (! $allowed) {
            return false;
        }

        $file = $this->file('file');
        $size = $file ? (int) $file->getSize() : 0;

        $enforcement = app(PlatformPlanEnforcementService::class);
        if (! $enforcement->canUploadFile((string) $user->tenant_id, $size)) {
            throw PlanLimitExceededException::forResource('storage');
        }

        return (string) $user->tenant_id !== '';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120'],
        ];
    }
}
