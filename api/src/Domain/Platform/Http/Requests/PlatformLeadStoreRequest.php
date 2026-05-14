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

    /**
     * Regras de validação.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => [
                'required',
                'string',
                // Aceita formatos BR com/sem máscara (10 ou 11 dígitos):
                // (11) 91234-5678 / 11912345678 / 11 91234 5678 / 1132345678
                'regex:/^\(?\d{2}\)?[\s-]?9?\d{4}[\s-]?\d{4}$/',
            ],
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
            'phone.regex' => 'Telefone inválido. Use o formato brasileiro, ex: (11) 91234-5678.',
            'lgpd_consent.accepted' => 'É necessário aceitar os termos de privacidade (LGPD).',
        ];
    }
}
