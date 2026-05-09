<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketEvaluation;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;

/**
 * Seeds chat ticket data for report testing.
 *
 * Covers: SLA Resolution, Agent Performance, CSAT/NPS, Chat Volume reports.
 */
final class ReportsChatSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('Nenhum tenant encontrado. Execute DatabaseSeeder primeiro.');

            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedTickets($tenant->id);
        }
    }

    private function seedTickets(string $tenantId): void
    {
        $statuses = ['open', 'pending', 'in_progress', 'closed'];
        $channels = ['whatsapp', 'telegram', 'webchat'];

        // Create 40 tickets with various statuses
        $tickets = ChatTicket::factory()
            ->count(min(40, 100))
            ->create([
                'tenant_id' => $tenantId,
                'status' => fn (): string => $statuses[array_rand($statuses)],
                'channel' => fn (): string => $channels[array_rand($channels)],
                'priority' => fn (): string => ['low', 'normal', 'high', 'urgent'][array_rand(['low', 'normal', 'high', 'urgent'])],
            ]);

        // Create extended data + evaluations for each ticket
        foreach ($tickets as $ticket) {
            // Create extended data directly (no factory available)
            \Domain\Chat\Models\ChatTicketExtended::query()->create([
                'id' => (string) \Illuminate\Support\Str::orderedUuid(),
                'ticket_id' => $ticket->id,
                'sla_first_response_breached' => (bool) random_int(0, 1),
                'sla_resolution_breached' => (bool) random_int(0, 1),
            ]);

            // Create evaluation for closed tickets
            if ($ticket->status === 'closed') {
                ChatTicketEvaluation::factory()
                    ->create([
                        'tenant_id' => $tenantId,
                        'ticket_id' => $ticket->id,
                    ]);
            }
        }
    }
}
