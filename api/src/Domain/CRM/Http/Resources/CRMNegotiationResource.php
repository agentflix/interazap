<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for CRM negotiation serialization.
 */
class CRMNegotiationResource extends BaseJsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'auth_user_id' => $this->auth_user_id,
            'crm_company_id' => $this->crm_company_id,
            'crm_contact_id' => $this->crm_contact_id,
            'company_id' => $this->crm_company_id,
            'contact_id' => $this->crm_contact_id,
            'crm_negotiation_funnel_id' => $this->crm_negotiation_funnel_id,
            'crm_negotiation_funnel_step_id' => $this->crm_negotiation_funnel_step_id,
            'funnel_id' => $this->crm_negotiation_funnel_id,
            'step_id' => $this->crm_negotiation_funnel_step_id,
            'crm_reason_loss_id' => $this->crm_reason_loss_id,
            'title' => $this->title,
            'amount' => (float) $this->amount,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'position' => (int) ($this->position ?? 0),
            'notes' => $this->notes,
            'expected_close' => $this->iso($this->expected_close),
            'closed_at' => $this->iso($this->closed_at),
            'funnel' => $this->whenLoaded('funnel', fn () => [
                'id' => $this->funnel->id,
                'name' => $this->funnel->name,
            ]),
            'step' => $this->whenLoaded('step', fn () => [
                'id' => $this->step->id,
                'name' => $this->step->name,
                'color' => $this->step->color,
                'order' => (int) $this->step->order,
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'avatar' => $this->user->avatar,
            ]),
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
            ]),
            'crm_company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'address' => $this->company->address,
                'city' => $this->company->city,
                'state' => $this->company->state,
                'zip_code' => $this->company->zip_code,
                'phone' => $this->company->phone,
            ]),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'address' => $this->company->address,
                'city' => $this->company->city,
                'state' => $this->company->state,
                'zip_code' => $this->company->zip_code,
                'phone' => $this->company->phone,
            ]),
            'custom_fields' => $this->whenLoaded('customFieldValues', fn () => $this->customFieldValues->map(fn ($value) => [
                'crm_custom_field_id' => $value->crm_custom_field_id,
                'value' => $value->value,
                'field' => [
                    'name' => $value->field?->name,
                    'type' => $value->field?->type,
                    'entity' => $value->field?->entity,
                    'options' => $value->field?->options,
                    'is_required' => $value->field?->is_required,
                ],
            ])),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
