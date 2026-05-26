<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para alteração da imagem de perfil da instância Uazapi.
 *
 * A imagem pode ser:
 * - URL HTTP/HTTPS para uma imagem JPEG
 * - String base64 (data:image/jpeg;base64,...)
 * - "remove" para remover a imagem atual
 */
final class PlatformUazapiInstanceProfileImageRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && (string) $this->user()->tenant_id !== '';
    }

    /**
     * Regras de validação para alteração da imagem de perfil da instância.
     *
     * @return array<string, array<int, string|\Closure>>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        $fail('The image must be a string.');

                        return;
                    }

                    if ($value === 'remove') {
                        return;
                    }

                    if (str_starts_with($value, 'data:image/jpeg;base64,')) {
                        $base64Data = substr($value, 23);
                        if (! preg_match('/^[A-Za-z0-9+\/]+=*$/', $base64Data)) {
                            $fail('Invalid base64 encoding for JPEG image.');
                        }

                        return;
                    }

                    if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                        if (! filter_var($value, FILTER_VALIDATE_URL)) {
                            $fail('Invalid URL format.');

                            return;
                        }
                        $parsed = parse_url($value);
                        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])) {
                            $fail('Invalid URL structure.');

                            return;
                        }

                        return;
                    }

                    $fail('The image must be a valid URL (http/https), base64 JPEG (data:image/jpeg;base64,...), or "remove".');
                },
            ],
        ];
    }
}
