<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Domain\Ai\Enums\AiDocumentType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for knowledge document upload.
 */
class UploadKnowledgeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('ai.knowledge.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $extensions = implode(',', AiDocumentType::allExtensions());

        return [
            'file' => [
                'required',
                'file',
                "mimes:{$extensions}",
                'max:10240', // 10MB max
            ],
            'name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A file is required.',
            'file.file' => 'The upload must be a valid file.',
            'file.mimes' => 'The file must be a TXT, CSV, Markdown, JSON, or PDF file.',
            'file.max' => 'The file may not be larger than 10MB.',
            'name.max' => 'The document name may not exceed 255 characters.',
        ];
    }
}
