<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BillingInvoice>
 */
class BillingInvoiceFactory extends Factory
{
    protected $model = BillingInvoice::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(BillingInvoiceStatus::cases());
        $dueDate = $this->faker->dateTimeBetween('-1 month', '+1 month');

        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'plan_id' => null,
            'reference_month' => $this->faker->date('Y-m'),
            'amount' => $this->faker->randomFloat(2, 10, 5000),
            'status' => $status->value,
            'due_date' => $dueDate->format('Y-m-d'),
            'paid_at' => null,
            'payment_method' => $this->faker->randomElement(['pix', 'credit_card']),
            'payment_url' => null,
            'asaas_payment_id' => null,
            'pix_payload' => null,
            'pix_qr_code_base64' => null,
            'metadata' => null,
        ];
    }
}
