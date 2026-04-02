<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Enums\AiNotificationChannel;
use Domain\Ai\Enums\AiNotificationReason;
use Domain\Ai\Models\AiSellerNotification;
use Domain\Auth\Models\AuthUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiSellerNotification>
 */
class AiSellerNotificationFactory extends Factory
{
    protected $model = AiSellerNotification::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'seller_id' => AuthUser::factory(),
            'message' => $this->faker->sentence(),
            'reason' => $this->faker->randomElement(AiNotificationReason::cases())->value,
            'channel' => $this->faker->randomElement(AiNotificationChannel::cases())->value,
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
            'attempts' => 0,
        ];
    }

    /**
     * Define a notificação como pendente.
     */
    public function pending(): self
    {
        return $this->state([
            'delivered_at' => null,
            'failed_at' => null,
        ]);
    }

    /**
     * Define a notificação como entregue.
     */
    public function delivered(): self
    {
        return $this->state([
            'delivered_at' => now(),
        ]);
    }

    /**
     * Define a notificação como falha.
     */
    public function failed(): self
    {
        return $this->state([
            'failed_at' => now(),
            'error_message' => $this->faker->sentence(),
        ]);
    }

    /**
     * Define prioridade urgente.
     */
    public function urgent(): self
    {
        return $this->state([
            'priority' => 'urgent',
        ]);
    }

    /**
     * Define canal email.
     */
    public function viaEmail(): self
    {
        return $this->state([
            'channel' => 'email',
        ]);
    }

    /**
     * Define canal WhatsApp.
     */
    public function viaWhatsApp(): self
    {
        return $this->state([
            'channel' => 'whatsapp',
        ]);
    }
}
