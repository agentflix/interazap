<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para captura pública de lead da plataforma InteraZap.
 *
 * Endpoint público (sem auth) — `authorize()` retorna `true`.
 * Anti-abuso: throttle aplicado na rota + honeypot opcional `website`.
 */
final class PlatformLeadStoreRequest extends FormRequest
{
    /**
     * Endpoint público — qualquer requisição é autorizada;
     * o controle de abuso é feito por throttle e honeypot.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($phone = $this->input('phone')) {
            $digits = preg_replace('/\D/', '', (string) $phone);
            $formatted = match (strlen($digits)) {
                11 => sprintf('(%s)%s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7)),
                10 => sprintf('(%s)%s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6)),
                default => $phone,
            };
            $this->merge(['phone' => $formatted]);
        }
    }

    /**
     * Regras de validação.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'celular_com_ddd'],
            'email' => ['required', 'email:rfc', 'max:180'],
            'company' => ['nullable', 'string', 'max:150'],
            'lgpd_consent' => ['required', 'accepted'],

            'utm_source' => ['nullable', 'string', 'max:80'],
            'utm_medium' => ['nullable', 'string', 'max:80'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'user_agent' => ['nullable', 'string', 'max:512'],

            // Honeypot — campo invisível ao usuário; se preenchido, indica bot.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Mensagens customizadas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.celular_com_ddd' => 'Telefone inválido. Use DDD + número, ex: 11912345678.',
            'lgpd_consent.accepted' => 'É necessário aceitar os termos de privacidade (LGPD).',
        ];
    }
}
