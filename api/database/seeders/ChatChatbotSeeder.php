<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatChatbotCooldown;
use Domain\Chat\Models\ChatChatbotRule;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChatChatbotSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(50)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('Nenhum tenant encontrado. Execute DatabaseSeeder primeiro.');

            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedTenantChatbot($tenant);
        }
    }

    private function seedTenantChatbot(PlatformTenant $tenant): void
    {
        $contacts = CRMContact::query()->where('tenant_id', $tenant->id)->get();

        if ($contacts->isEmpty()) {
            return;
        }

        $instance = ChatInstance::query()->where('tenant_id', $tenant->id)->first();

        if (! $instance) {
            $instance = ChatInstance::factory()->create([
                'tenant_id' => $tenant->id,
                'provider' => 'uazapi',
                'name' => 'Seeded Instance',
                'status' => 'connected',
                'mode' => 'production',
            ]);
        }

        $agents = AuthUser::query()->where('tenant_id', $tenant->id)->get();
        $agent = $agents->random() ?? AuthUser::query()->where('tenant_id', $tenant->id)->first();

        $rulesData = [
            [
                'name' => 'Boas-vindas',
                'trigger_text' => 'oi',
                'response_text' => 'Oi! Eu sou o assistente virtual da AgentFlix. Em que posso ajudar?',
                'is_welcome' => true,
                'cooldown_seconds' => 3600,
            ],
            [
                'name' => 'Suporte',
                'trigger_text' => 'suporte',
                'response_text' => 'Claro! Vou encaminhar seu pedido para o time de suporte.',
                'is_welcome' => false,
                'cooldown_seconds' => 900,
            ],
            [
                'name' => 'Planos',
                'trigger_text' => 'planos',
                'response_text' => 'Temos planos mensal e anual. Qual voce gostaria de conhecer?',
                'is_welcome' => false,
                'cooldown_seconds' => 1200,
            ],
            [
                'name' => 'Financeiro',
                'trigger_text' => 'boleto',
                'response_text' => 'Para boletos e pagamentos, posso te enviar um link seguro. Quer continuar?',
                'is_welcome' => false,
                'cooldown_seconds' => 1800,
            ],
            [
                'name' => 'Treinamento',
                'trigger_text' => 'treinamento',
                'response_text' => 'Podemos agendar um treinamento com nosso time de CS. Qual o melhor horario?',
                'is_welcome' => false,
                'cooldown_seconds' => 1800,
            ],
            [
                'name' => 'Encerramento',
                'trigger_text' => 'tchau',
                'response_text' => 'Obrigado pelo contato! Se precisar, estarei por aqui.',
                'is_welcome' => false,
                'cooldown_seconds' => 900,
            ],
        ];

        $rules = collect();
        foreach ($rulesData as $ruleData) {
            $rules->push(ChatChatbotRule::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => $ruleData['name'],
                'trigger_text' => $ruleData['trigger_text'],
                'response_text' => $ruleData['response_text'],
                'is_active' => true,
                'is_welcome' => $ruleData['is_welcome'],
                'cooldown_seconds' => $ruleData['cooldown_seconds'],
            ]));
        }

        ChatMessageTemplate::factory()
            ->count(4)
            ->state(fn (): array => [
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ])
            ->create();

        $tickets = ChatTicket::factory()
            ->count(rand(10, 20))
            ->forTenant($tenant->id)
            ->sequence(function ($sequence) use ($contacts, $instance): array {
                $contact = $contacts->random();
                $phoneE164 = $contact->whatsapp ?: $contact->phone;
                if (! $phoneE164) {
                    $phoneE164 = '+55'.fake()->numerify('###########');
                }

                // Use sequence index and tenant ID to ensure unique remote_jid across tenants
                $remoteJid = ltrim($phoneE164, '+').'-'.substr($instance->tenant_id, 0, 4).'-'.$sequence->index.'@wa';

                return [
                    'contact_id' => $contact->id,
                    'instance_id' => $instance->id,
                    'remote_jid' => $remoteJid,
                    'phone' => $phoneE164,
                    'phone_e164' => $phoneE164,
                    'push_name' => $contact->name,
                    'status' => fake()->randomElement(['pending', 'in_progress', 'closed']),
                    'priority' => fake()->randomElement(['low', 'normal', 'high']),
                    'subject' => fake()->sentence(3),
                    'started_at' => now()->subDays(fake()->numberBetween(1, 30)),
                    'last_message_at' => now()->subMinutes(fake()->numberBetween(5, 180)),
                    'is_bot_active' => fake()->boolean(70),
                    'protocol' => 'TKT-'.fake()->unique()->numerify('#####'),
                ];
            })
            ->create();

        foreach ($tickets as $ticket) {
            $messageCount = fake()->numberBetween(2, 6);

            for ($i = 0; $i < $messageCount; $i++) {
                $isIncoming = fake()->boolean(50);
                $sentAt = now()->subMinutes(fake()->numberBetween(1, 240));

                ChatMessage::factory()
                    ->state(fn (): array => [
                        'tenant_id' => $tenant->id,
                        'ticket_id' => $ticket->id,
                        'contact_id' => $ticket->contact_id,
                        'user_id' => $isIncoming ? null : $agent->id,
                        'direction' => $isIncoming ? 'incoming' : 'outgoing',
                        'is_from_contact' => $isIncoming,
                        'source' => $isIncoming ? 'contact' : 'agent',
                        'status' => 'sent',
                        'sent_at' => $sentAt,
                        'delivered_at' => $sentAt->copy()->addMinutes(1),
                    ])
                    ->create();
            }
        }

        $cooldownTargets = $tickets->shuffle()->take(5);
        foreach ($cooldownTargets as $ticket) {
            $rule = $rules->random();

            ChatChatbotCooldown::factory()
                ->state(fn (): array => [
                    'tenant_id' => $tenant->id,
                    'ticket_id' => $ticket->id,
                    'rule_id' => $rule->id,
                    'cooldown_until' => now()->addMinutes(fake()->numberBetween(15, 90)),
                ])
                ->create();
        }

        $this->command->info(sprintf('Tenant %s: Chatbot rules and %d tickets created.', $tenant->tenant_code, $tickets->count()));
    }
}
