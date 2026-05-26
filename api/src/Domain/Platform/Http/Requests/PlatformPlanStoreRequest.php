<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Domain\Billing\Enums\OverageMode;
use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validação para criação de plano da plataforma.
 */
final class PlatformPlanStoreRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->check('create', PlatformPlan::class);
    }

    /**
     * Regras de validação para criação de plano.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:50', Rule::unique('platform_plans', 'slug')],
            'limit_users' => ['required', 'integer', 'min:0'],
            'storage_mode' => ['required', 'string', Rule::in(array_map(fn ($c) => $c->value, PlatformStorageMode::cases()))],
            'storage_limit_bytes' => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(fn () => $this->input('storage_mode') === PlatformStorageMode::LIMITED->value),
            ],
            'ai_enabled' => ['required', 'boolean'],
            'message_limit_monthly' => ['required', 'integer', 'min:0'],
            'overage_mode' => ['required', 'string', Rule::in(array_map(fn ($c) => $c->value, OverageMode::cases()))],
            'overage_price_per_message' => ['nullable', 'numeric', 'min:0'],
            'chat_channels_limit' => ['required', 'integer', 'min:0'],
            'negotiations_mode' => ['required', 'string', Rule::in(array_map(fn ($c) => $c->value, PlatformNegotiationsMode::cases()))],
            'negotiations_limit' => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(fn () => $this->input('negotiations_mode') === PlatformNegotiationsMode::LIMITED->value),
            ],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Mensagens customizadas para erros de validação.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Slug já está em uso.',
            'storage_limit_bytes.required' => 'Limite de storage é obrigatório para planos limitados.',
            'negotiations_limit.required' => 'Limite de negociações é obrigatório para planos limitados.',
        ];
    }
}
