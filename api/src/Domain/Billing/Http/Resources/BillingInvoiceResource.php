<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Resources;

use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource para serialização de Faturas de Billing.
 *
 * Inclui label e cor de status localizados para uso no frontend.
 */
final class BillingInvoiceResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        $status = $this->status;
        $statusValue = $status instanceof BillingInvoiceStatus ? $status->value : $status;
        $statusLabel = $status instanceof BillingInvoiceStatus ? $status->label() : $status;
        $statusColor = $status instanceof BillingInvoiceStatus ? $status->color() : 'default';

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_id' => $this->plan_id,
            'reference_month' => $this->reference_month,
            'amount' => (float) $this->amount,
            'status' => $statusValue,
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'paid_at' => $this->iso($this->paid_at),
            'payment_method' => $this->payment_method,
            'payment_url' => $this->payment_url,
            'asaas_payment_id' => $this->asaas_payment_id,
            'has_pix' => ! empty($this->pix_payload),
            'plan' => $this->whenLoaded('plan', fn () => [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
            ]),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
