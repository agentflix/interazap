<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for CRM negotiation.
 *
 * @readonly
 */
final readonly class CRMNegotiationDTO
{
    public function __construct(
        public string $title,
        public float $amount,
        public string $crm_negotiation_funnel_id,
        public string $crm_negotiation_funnel_step_id,
        public string $crm_contact_id,
        public ?string $auth_user_id = null,
        public ?string $crm_company_id = null,
        public ?string $crm_reason_loss_id = null,
        public ?string $notes = null,
        public string $status = 'open',
        public int $position = 0,
        public ?string $expected_close = null,
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: (string) ($data['title'] ?? ''),
            amount: isset($data['amount']) ? (float) $data['amount'] : 0.0,
            crm_negotiation_funnel_id: (string) ($data['crm_negotiation_funnel_id'] ?? ''),
            crm_negotiation_funnel_step_id: (string) ($data['crm_negotiation_funnel_step_id'] ?? ''),
            crm_contact_id: (string) ($data['crm_contact_id'] ?? ''),
            auth_user_id: $data['auth_user_id'] ?? null,
            crm_company_id: $data['crm_company_id'] ?? null,
            crm_reason_loss_id: $data['crm_reason_loss_id'] ?? null,
            notes: $data['notes'] ?? null,
            status: (string) ($data['status'] ?? 'open'),
            position: (int) ($data['position'] ?? 0),
            expected_close: $data['expected_close'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'amount' => $this->amount,
            'crm_negotiation_funnel_id' => $this->crm_negotiation_funnel_id,
            'crm_negotiation_funnel_step_id' => $this->crm_negotiation_funnel_step_id,
            'auth_user_id' => $this->auth_user_id,
            'crm_company_id' => $this->crm_company_id,
            'crm_contact_id' => $this->crm_contact_id,
            'crm_reason_loss_id' => $this->crm_reason_loss_id,
            'notes' => $this->notes,
            'status' => $this->status,
            'position' => $this->position,
            'expected_close' => $this->expected_close,
        ];
    }
}
