<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Billing\Enums\BillingPaymentStatus;
use Domain\Billing\Models\BillingPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BillingPayment>
 */
class BillingPaymentFactory extends Factory
{
    protected $model = BillingPayment::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'invoice_id' => \Domain\Billing\Models\BillingInvoice::query()->inRandomOrder()->first() ?? \Domain\Billing\Models\BillingInvoice::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 5000),
            'payment_method' => $this->faker->randomElement(['pix', 'credit_card']),
            'provider' => 'asaas',
            'provider_payment_id' => $this->faker->uuid(),
            'status' => BillingPaymentStatus::CONFIRMED->value,
            'confirmed_at' => null,
            'metadata' => null,
        ];
    }
}
