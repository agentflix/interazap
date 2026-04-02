<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Enums\AiPostSaleScheduleType;
use Domain\Ai\Models\AiPostSaleSchedule;
use Domain\CRM\Enums\CRMNegotiationStatus;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;

/**
 * Seed D+1, D+7 and D+30 post-sale schedules for won negotiations.
 */
class AiPostSaleScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping AiPostSaleScheduleSeeder.');

            return;
        }

        $total = 0;

        foreach ($tenants as $tenant) {
            $wonNegotiations = CRMNegotiation::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', CRMNegotiationStatus::WON->value)
                ->get();

            if ($wonNegotiations->isEmpty()) {
                continue;
            }

            foreach ($wonNegotiations as $negotiation) {
                $saleDate = $negotiation->closed_at ?? $negotiation->updated_at ?? now();

                foreach ([AiPostSaleScheduleType::D1, AiPostSaleScheduleType::D7, AiPostSaleScheduleType::D30] as $type) {
                    [$status, $sentAt] = $this->statusForType($type);

                    AiPostSaleSchedule::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'negotiation_id' => $negotiation->id,
                            'schedule_type' => $type->value,
                        ],
                        [
                            'ticket_id' => null,
                            'sale_date' => $saleDate,
                            'scheduled_at' => (clone $saleDate)->addDays($type->daysOffset()),
                            'status' => $status,
                            'sent_at' => $sentAt,
                            'message_id' => $sentAt !== null ? sprintf('msg-%s-%s', $type->value, $negotiation->id) : null,
                            'error_message' => $status === 'failed' ? 'Messaging provider error.' : null,
                            'attempts' => random_int(0, 2),
                            'custom_message' => null,
                            'metadata' => ['seed_source' => 'ai_module_seeder'],
                        ]
                    );

                    $total++;
                }
            }
        }

        $this->command->info(sprintf('AI Post-Sale Schedules seeded: %d', $total));
    }

    /**
     * @return array{0: string, 1: \Illuminate\Support\Carbon|null}
     */
    private function statusForType(AiPostSaleScheduleType $type): array
    {
        $rand = random_int(1, 100);

        if ($type === AiPostSaleScheduleType::D1) {
            if ($rand <= 90) {
                return ['sent', now()->subDays(random_int(1, 20))];
            }

            return ['pending', null];
        }

        if ($type === AiPostSaleScheduleType::D7) {
            if ($rand <= 60) {
                return ['sent', now()->subDays(random_int(1, 20))];
            }

            if ($rand <= 80) {
                return ['pending', null];
            }

            return ['failed', null];
        }

        if ($rand <= 30) {
            return ['sent', now()->subDays(random_int(1, 20))];
        }

        if ($rand <= 80) {
            return ['pending', null];
        }

        if ($rand <= 90) {
            return ['cancelled', null];
        }

        return ['failed', null];
    }
}
