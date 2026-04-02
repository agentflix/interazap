<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMEvent;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CRMEventSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, string>
     */
    private const LOCATIONS = [
        'Av. Paulista, 1578 - Bela Vista, São Paulo/SP',
        'Rua da Bahia, 1148 - Centro, Belo Horizonte/MG',
        'Av. Atlântica, 1702 - Copacabana, Rio de Janeiro/RJ',
        'Av. Beira Mar, 2450 - Meireles, Fortaleza/CE',
        'Setor Bancário Sul, Qd. 2 - Asa Sul, Brasília/DF',
        'Rua Chile, 65 - Centro Histórico, Salvador/BA',
        'Av. Boa Viagem, 5110 - Boa Viagem, Recife/PE',
        'Rua XV de Novembro, 1299 - Centro, Curitiba/PR',
    ];

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('Nenhum tenant encontrado. Execute DatabaseSeeder primeiro.');

            return;
        }

        $total = 0;

        foreach ($tenants as $tenant) {
            $user = AuthUser::query()->where('tenant_id', $tenant->id)->first();
            $statuses = CRMEvent::statuses();
            $types = CRMEvent::types();
            $recurrences = CRMEvent::recurrences();

            $events = CRMEvent::factory()
                ->count(min(24, 100))
                ->state(function () use ($tenant, $user, $statuses, $types, $recurrences): array {
                    $startsAt = fake()->dateTimeBetween('-15 days', '+20 days');
                    $endsAt = (clone $startsAt)->modify('+'.random_int(30, 120).' minutes');
                    $recurrence = fake()->randomElement($recurrences);

                    return [
                        'tenant_id' => $tenant->id,
                        'auth_user_id' => $user?->id,
                        'status' => fake()->randomElement($statuses),
                        'type' => fake()->randomElement($types),
                        'location' => self::LOCATIONS[array_rand(self::LOCATIONS)],
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'is_all_day' => false,
                        'recurrence' => $recurrence,
                        'recurrence_ends_at' => $recurrence === CRMEvent::RECURRENCE_NONE
                            ? null
                            : now()->addDays(random_int(5, 45)),
                        'color' => fake()->hexColor(),
                    ];
                })
                ->create();

            $total += $events->count();
        }

        $this->command->info(sprintf('Events created: %d', $total));
    }
}
