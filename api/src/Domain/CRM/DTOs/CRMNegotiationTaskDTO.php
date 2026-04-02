<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for negotiation task.
 *
 * @readonly
 */
final readonly class CRMNegotiationTaskDTO
{
    public function __construct(
        public string $crm_negotiation_id,
        public string $title,
        public ?string $description = null,
        public ?string $due_date = null,
        public string $status = 'pending',
        public ?string $auth_user_id = null,
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request, string $negotiationId): self
    {
        return self::fromArray($request->validated(), $negotiationId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $negotiationId = ''): self
    {
        return new self(
            crm_negotiation_id: $negotiationId ?: ($data['crm_negotiation_id'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            description: $data['description'] ?? null,
            due_date: $data['due_date'] ?? null,
            status: (string) ($data['status'] ?? 'pending'),
            auth_user_id: $data['auth_user_id'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'crm_negotiation_id' => $this->crm_negotiation_id,
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'auth_user_id' => $this->auth_user_id,
        ];
    }
}
