<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Enums\AiPostSaleScheduleType;
use Domain\Ai\Enums\AiPostSaleStatus;
use Domain\Ai\Models\AiPostSaleSchedule;
use Domain\CRM\Models\CRMNegotiation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiPostSaleSchedule>
 */
class AiPostSaleScheduleFactory extends Factory
{
    protected $model = AiPostSaleSchedule::class;

    public function definition(): array
    {
        $tenant = \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory();
        $scheduleType = $this->faker->randomElement(AiPostSaleScheduleType::cases());
        $saleDate = now()->subDays($this->faker->numberBetween(1, 30));

        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant,
            'negotiation_id' => CRMNegotiation::factory()->state(['tenant_id' => $tenant]),
            'ticket_id' => null,
            'schedule_type' => $scheduleType->value,
            'sale_date' => $saleDate,
            'scheduled_at' => $saleDate->copy()->addDays($scheduleType->daysOffset()),
            'status' => AiPostSaleStatus::PENDING->value,
            'attempts' => 0,
        ];
    }

    /**
     * Status pendente.
     */
    public function pending(): self
    {
        return $this->state([
            'status' => AiPostSaleStatus::PENDING->value,
        ]);
    }

    /**
     * Status enviado.
     */
    public function sent(): self
    {
        return $this->state([
            'status' => AiPostSaleStatus::SENT->value,
            'sent_at' => now(),
            'message_id' => (string) Str::orderedUuid(),
        ]);
    }

    /**
     * Status falha.
     */
    public function failed(): self
    {
        return $this->state([
            'status' => AiPostSaleStatus::FAILED->value,
            'error_message' => $this->faker->sentence(),
        ]);
    }

    /**
     * Status cancelado.
     */
    public function cancelled(): self
    {
        return $this->state([
            'status' => AiPostSaleStatus::CANCELLED->value,
        ]);
    }

    /**
     * D+1 schedule.
     */
    public function dPlus1(): self
    {
        $saleDate = now()->subDay();

        return $this->state([
            'schedule_type' => AiPostSaleScheduleType::D1->value,
            'sale_date' => $saleDate,
            'scheduled_at' => $saleDate->copy()->addDay(),
        ]);
    }

    /**
     * D+7 schedule.
     */
    public function dPlus7(): self
    {
        $saleDate = now()->subDays(7);

        return $this->state([
            'schedule_type' => AiPostSaleScheduleType::D7->value,
            'sale_date' => $saleDate,
            'scheduled_at' => $saleDate->copy()->addDays(7),
        ]);
    }

    /**
     * D+30 schedule.
     */
    public function dPlus30(): self
    {
        $saleDate = now()->subDays(30);

        return $this->state([
            'schedule_type' => AiPostSaleScheduleType::D30->value,
            'sale_date' => $saleDate,
            'scheduled_at' => $saleDate->copy()->addDays(30),
        ]);
    }

    /**
     * Due (ready to be sent).
     */
    public function due(): self
    {
        return $this->pending()->state([
            'scheduled_at' => now()->subHour(),
        ]);
    }
}
