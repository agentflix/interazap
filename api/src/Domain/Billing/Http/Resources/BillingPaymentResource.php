<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Resources;

use Domain\Billing\Enums\BillingPaymentStatus;
use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Payment serialization.
 */
final class BillingPaymentResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        $status = $this->status;
        $statusValue = $status instanceof BillingPaymentStatus ? $status->value : $status;
        $statusLabel = $status instanceof BillingPaymentStatus ? $status->label() : $status;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'invoice_id' => $this->invoice_id,
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'provider' => $this->provider,
            'provider_payment_id' => $this->provider_payment_id,
            'status' => $statusValue,
            'status_label' => $statusLabel,
            'confirmed_at' => $this->iso($this->confirmed_at),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
