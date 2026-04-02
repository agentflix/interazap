<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for commercial proposal.
 *
 * @readonly
 */
final readonly class CRMProposalDTO
{
    /**
     * @param  list<CRMProposalItemDTO>  $items
     */
    public function __construct(
        public string $crm_negotiation_id,
        public string $title,
        public ?int $number = null,
        public string $status = 'draft',
        public ?string $valid_until = null,
        public ?string $notes = null,
        public array $items = [],
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request, string $negotiationId, string $defaultStatus = 'draft'): self
    {
        return self::fromArray($request->validated(), $negotiationId, $defaultStatus);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $negotiationId = '', string $defaultStatus = 'draft'): self
    {
        $items = array_values(array_map(
            static fn (array $item): CRMProposalItemDTO => CRMProposalItemDTO::fromArray($item),
            $data['items'] ?? [],
        ));

        return new self(
            crm_negotiation_id: $negotiationId ?: ($data['crm_negotiation_id'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            number: isset($data['number']) ? (int) $data['number'] : null,
            status: (string) ($data['status'] ?? $defaultStatus),
            valid_until: $data['valid_until'] ?? null,
            notes: $data['notes'] ?? null,
            items: $items,
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
            'number' => $this->number,
            'status' => $this->status,
            'valid_until' => $this->valid_until,
            'notes' => $this->notes,
        ];
    }
}
