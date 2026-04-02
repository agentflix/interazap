<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTransmissionList;
use Domain\Chat\Models\ChatTransmissionListContact;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChatTransmissionListSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenant = PlatformTenant::query()->first();

        if (! $tenant) {
            $this->command->warn('Nenhum tenant encontrado. Execute DatabaseSeeder primeiro.');

            return;
        }

        $contacts = CRMContact::query()->where('tenant_id', $tenant->id)->get();

        if ($contacts->isEmpty()) {
            $this->command->warn('Nenhum contato encontrado. Execute CRMContactSeeder primeiro.');

            return;
        }

        $instance = ChatInstance::query()->where('tenant_id', $tenant->id)->first();

        $transmissionListsData = [
            [
                'name' => 'Reativacao de Leads',
                'status' => 'scheduled',
                'scheduled_at' => now()->addDays(2),
                'message' => 'Ola! Posso ajudar com alguma duvida sobre nossos planos?',
                'filter_criteria' => ['segment' => 'inactive'],
            ],
            [
                'name' => 'Lembrete de Treinamento',
                'status' => 'running',
                'scheduled_at' => now()->subHours(2),
                'message' => 'Seu treinamento esta confirmado. Posso enviar o link da reuniao?',
                'filter_criteria' => ['tag' => 'onboarding'],
            ],
            [
                'name' => 'Oferta de Upgrade',
                'status' => 'draft',
                'scheduled_at' => null,
                'message' => 'Preparamos uma oferta especial de upgrade para voce. Quer conhecer?',
                'filter_criteria' => ['plan' => 'starter'],
            ],
            [
                'name' => 'Pesquisa de Satisfacao',
                'status' => 'completed',
                'scheduled_at' => now()->subDays(1),
                'message' => 'Como foi sua experiencia recente com nosso suporte? Responda com uma nota de 0 a 10.',
                'filter_criteria' => ['recent_ticket' => true],
            ],
            [
                'name' => 'Boas-vindas',
                'status' => 'scheduled',
                'scheduled_at' => now()->addHours(6),
                'message' => 'Bem-vindo(a) ao InteraZap! Estamos prontos para ajudar na sua jornada.',
                'filter_criteria' => ['segment' => 'new'],
            ],
        ];

        $transmissionLists = collect();

        foreach ($transmissionListsData as $transmissionListData) {
            $transmissionLists->push(ChatTransmissionList::factory()->create([
                'tenant_id' => $tenant->id,
                'instance_id' => $instance?->id,
                'name' => $transmissionListData['name'],
                'status' => $transmissionListData['status'],
                'scheduled_at' => $transmissionListData['scheduled_at'],
                'message' => $transmissionListData['message'],
                'filter_criteria' => $transmissionListData['filter_criteria'],
                'metadata' => ['env' => 'seed'],
            ]));
        }

        foreach ($transmissionLists as $transmissionList) {
            $contactsForTransmissionList = $contacts->shuffle()->take(fake()->numberBetween(8, 18));

            foreach ($contactsForTransmissionList as $contact) {
                $status = fake()->randomElement(['pending', 'sent', 'failed']);

                ChatTransmissionListContact::query()->create([
                    'tenant_id' => $tenant->id,
                    'transmission_list_id' => $transmissionList->id,
                    'contact_id' => $contact->id,
                    'status' => $status,
                    'sent_at' => $status === 'sent' ? now()->subMinutes(fake()->numberBetween(5, 180)) : null,
                    'error' => $status === 'failed' ? 'Falha ao enviar mensagem.' : null,
                ]);
            }
        }

        $this->command->info(sprintf('Chat transmission lists created: %d', $transmissionLists->count()));
    }
}
