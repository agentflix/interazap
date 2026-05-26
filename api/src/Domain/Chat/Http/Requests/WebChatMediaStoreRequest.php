<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para upload de mídia via Webchat público.
 *
 * A autorização real é feita no controller via JWT.
 * Este FormRequest apenas valida o payload da requisição.
 *
 * @category Requests
 */
final class WebChatMediaStoreRequest extends FormRequest
{
    /**
     * Autorização via JWT validado no controller — retorna sempre true aqui.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define as regras de validação que se aplicam à requisição.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mp3,ogg,wav,pdf,doc,docx,xls,xlsx',
            ],
        ];
    }

    /**
     * Mensagens de erro personalizadas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'O token de sessão é obrigatório.',
            'token.string' => 'O token deve ser uma string válida.',
            'file.required' => 'O arquivo é obrigatório.',
            'file.file' => 'O campo enviado deve ser um arquivo.',
            'file.max' => 'O arquivo não pode ser maior que 5 MB.',
            'file.mimes' => 'Tipo de arquivo não permitido. Formatos aceitos: jpg, jpeg, png, gif, webp, mp4, mov, avi, mp3, ogg, wav, pdf, doc, docx, xls, xlsx.',
        ];
    }
}
