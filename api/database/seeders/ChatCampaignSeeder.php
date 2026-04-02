<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Chat\Models\ChatCampaign;
use Domain\Chat\Models\ChatCampaignContact;
use Domain\Chat\Models\ChatInstance;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChatCampaignSeeder extends Seeder
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

        $campaignsData = [
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
                'message' => 'Bem-vindo(a) ao AgentFlix! Estamos prontos para ajudar na sua jornada.',
                'filter_criteria' => ['segment' => 'new'],
            ],
        ];

        $campaigns = collect();

        foreach ($campaignsData as $campaignData) {
            $campaigns->push(ChatCampaign::factory()->create([
                'tenant_id' => $tenant->id,
                'instance_id' => $instance?->id,
                'name' => $campaignData['name'],
                'status' => $campaignData['status'],
                'scheduled_at' => $campaignData['scheduled_at'],
                'message' => $campaignData['message'],
                'filter_criteria' => $campaignData['filter_criteria'],
                'metadata' => ['env' => 'seed'],
            ]));
        }

        foreach ($campaigns as $campaign) {
            $contactsForCampaign = $contacts->shuffle()->take(fake()->numberBetween(8, 18));

            foreach ($contactsForCampaign as $contact) {
                $status = fake()->randomElement(['pending', 'sent', 'failed']);

                ChatCampaignContact::query()->create([
                    'tenant_id' => $tenant->id,
                    'campaign_id' => $campaign->id,
                    'contact_id' => $contact->id,
                    'status' => $status,
                    'sent_at' => $status === 'sent' ? now()->subMinutes(fake()->numberBetween(5, 180)) : null,
                    'error' => $status === 'failed' ? 'Falha ao enviar mensagem.' : null,
                ]);
            }
        }

        $this->command->info(sprintf('Chat campaigns created: %d', $campaigns->count()));
    }
}
