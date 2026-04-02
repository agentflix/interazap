<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds realistic chat conversation scenarios for UI testing.
 */
class ChatConversationScenarioSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed tickets and messages covering queue, open, in-progress, closed,
     * AI-driven, human-driven and mixed conversations.
     */
    public function run(): void
    {
        $adminUser = AuthUser::query()->withoutGlobalScopes()
            ->where('email', 'admin@agentflix.com.br')->latest()
            ->first();

        $preferredTenant = null;

        if ($adminUser?->tenant_id) {
            $preferredTenant = PlatformTenant::query()->withoutGlobalScopes()->find($adminUser->tenant_id);
        }

        if (! $preferredTenant) {
            $preferredTenant = PlatformTenant::query()
                ->where('tenant_code', 'AGENTFLX')
                ->orWhere('primary_email', 'admin@agentflix.com.br')
                ->first() ?? PlatformTenant::query()->first();
        }

        if (! $preferredTenant) {
            $this->command->warn('Nenhum tenant encontrado para cenários de chat.');

            return;
        }

        $tenants = PlatformTenant::query()
            ->orderByRaw(
                'case when id = ? then 0 else 1 end',
                [$preferredTenant->id],
            )
            ->get();

        $scenarios = [
            [
                'key' => 'queue-ai-critical',
                'subject' => '[SEED] IA na fila - crítico',
                'status' => 'pending',
                'is_bot_active' => true,
                'human_takeover_at' => null,
                'assigned_to' => false,
                'priority' => 'urgent',
                'sentiment' => 'negative',
                'sentiment_score' => 94,
                'messages' => [
                    ['incoming', 'text', 'webhook', true, 'Olá, estou muito insatisfeito com a cobrança duplicada.', 'read'],
                    ['incoming', 'audio', 'webhook', true, 'Áudio recebido do cliente', 'delivered'],
                ],
            ],
            [
                'key' => 'open-ai-attention',
                'subject' => '[SEED] IA aberto - atenção',
                'status' => 'open',
                'is_bot_active' => true,
                'human_takeover_at' => null,
                'assigned_to' => true,
                'priority' => 'high',
                'sentiment' => 'neutral',
                'sentiment_score' => 71,
                'messages' => [
                    ['incoming', 'text', 'webhook', true, 'Preciso de ajuda para ativar minha integração.', 'read'],
                    ['outgoing', 'text', 'bot', false, 'Olá! Posso te guiar no passo a passo da ativação.', 'sent'],
                    ['incoming', 'text', 'webhook', true, 'Perfeito, pode continuar.', 'delivered'],
                ],
            ],
            [
                'key' => 'in-progress-human',
                'subject' => '[SEED] Humano em atendimento',
                'status' => 'in_progress',
                'is_bot_active' => false,
                'human_takeover_at' => now()->subMinutes(30),
                'assigned_to' => true,
                'priority' => 'normal',
                'sentiment' => 'neutral',
                'sentiment_score' => 53,
                'messages' => [
                    ['incoming', 'text', 'webhook', true, 'Quero falar com um atendente humano.', 'read'],
                    ['outgoing', 'text', 'agent', false, 'Olá, sou da equipe comercial e vou continuar seu atendimento.', 'sent'],
                    ['incoming', 'document', 'webhook', true, 'Segue meu contrato para análise.', 'delivered'],
                ],
            ],
            [
                'key' => 'closed-human',
                'subject' => '[SEED] Fechado com humano',
                'status' => 'closed',
                'is_bot_active' => false,
                'human_takeover_at' => now()->subHours(6),
                'assigned_to' => true,
                'priority' => 'normal',
                'sentiment' => 'positive',
                'sentiment_score' => 18,
                'close_reason' => 'Solicitação resolvida com sucesso',
                'messages' => [
                    ['incoming', 'text', 'webhook', true, 'Conseguimos resolver por aqui, obrigado!', 'read'],
                    ['outgoing', 'text', 'agent', false, 'Perfeito, vou encerrar este chamado. Estamos à disposição.', 'read'],
                    ['outgoing', 'text', 'system', false, 'Chamado encerrado automaticamente.', 'delivered'],
                ],
            ],
            [
                'key' => 'mixed-bot-human',
                'subject' => '[SEED] IA + Humano (handover)',
                'status' => 'in_progress',
                'is_bot_active' => false,
                'human_takeover_at' => now()->subMinutes(12),
                'assigned_to' => true,
                'priority' => 'high',
                'sentiment' => 'negative',
                'sentiment_score' => 79,
                'messages' => [
                    ['incoming', 'text', 'webhook', true, 'Meu pedido está atrasado e ninguém responde.', 'read'],
                    ['outgoing', 'text', 'bot', false, 'Estou consultando seu histórico agora mesmo.', 'delivered'],
                    ['outgoing', 'text', 'agent', false, 'Assumi o caso e vou priorizar sua entrega hoje.', 'sent'],
                ],
            ],
            [
                'key' => 'queue-media',
                'subject' => '[SEED] Fila com mídia',
                'status' => 'pending',
                'is_bot_active' => true,
                'human_takeover_at' => null,
                'assigned_to' => false,
                'priority' => 'normal',
                'sentiment' => 'neutral',
                'sentiment_score' => 46,
                'messages' => [
                    ['incoming', 'image', 'webhook', true, 'Cliente enviou comprovante em imagem.', 'delivered'],
                    ['incoming', 'location', 'webhook', true, 'Cliente compartilhou localização para visita técnica.', 'pending'],
                ],
            ],
            [
                'key' => 'open-positive',
                'subject' => '[SEED] Aberto positivo',
                'status' => 'open',
                'is_bot_active' => true,
                'human_takeover_at' => null,
                'assigned_to' => true,
                'priority' => 'low',
                'sentiment' => 'positive',
                'sentiment_score' => 22,
                'messages' => [
                    ['incoming', 'text', 'webhook', true, 'Quero contratar mais uma licença.', 'read'],
                    ['outgoing', 'text', 'bot', false, 'Ótima notícia! Posso te mostrar os planos disponíveis.', 'sent'],
                ],
            ],
            [
                'key' => 'resolved-ai',
                'subject' => '[SEED] Resolvido por IA',
                'status' => 'resolved',
                'is_bot_active' => true,
                'human_takeover_at' => null,
                'assigned_to' => false,
                'priority' => 'normal',
                'sentiment' => 'positive',
                'sentiment_score' => 28,
                'messages' => [
                    ['incoming', 'text', 'webhook', true, 'Como altero minha senha?', 'read'],
                    ['outgoing', 'text', 'bot', false, 'Você pode alterar em Configurações > Segurança > Trocar senha.', 'read'],
                    ['incoming', 'text', 'webhook', true, 'Perfeito, funcionou!', 'read'],
                ],
            ],
        ];

        $seededTenants = 0;

        foreach ($tenants as $tenant) {
            $instance = ChatInstance::query()->withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'Default Uazapi',
                ],
                [
                    'id' => (string) Str::orderedUuid(),
                    'provider' => 'uazapi',
                    'mode' => 'production',
                    'status' => 'connected',
                    'is_active' => true,
                    'evaluation_enabled' => true,
                    'webhook_token' => (string) Str::random(40),
                    'settings_json' => ['seeded' => true],
                    'last_status_at' => now(),
                ],
            );

            if ($instance->status !== 'connected') {
                $instance->update([
                    'status' => 'connected',
                    'is_active' => true,
                    'last_status_at' => now(),
                ]);
            }

            $users = AuthUser::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $contacts = CRMContact::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->limit(12)
                ->get();

            if ($contacts->isEmpty()) {
                $fallbackContact = CRMContact::query()->withoutGlobalScopes()->create([
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $tenant->id,
                    'name' => 'Contato Seed '.mb_substr((string) ($tenant->tenant_code ?? 'TENANT'), 0, 8),
                    'phone' => '+5511999999999',
                    'whatsapp' => '5511999999999',
                    'email' => sprintf('seed.chat.%s@agentflix.local', strtolower((string) ($tenant->tenant_code ?? 'tenant'))),
                    'is_active' => true,
                ]);

                $contacts = collect([$fallbackContact]);
            }

            if ($contacts->isEmpty()) {
                continue;
            }

            $hasUsers = $users->isNotEmpty();

            foreach ($scenarios as $index => $scenario) {
                // Ensure unique contact per scenario to avoid unique_open_ticket_per_jid constraint
                $contact = $contacts->get($index % $contacts->count());

                // If this contact already has an open ticket in this run, we need a different one or different JID
                $remoteJid = sprintf('55119%08d@s.whatsapp.net', 90000000 + ($index * 100) + $seededTenants);

                $baseDate = \Illuminate\Support\Facades\Date::now()->subHours((($index + 1) * 2));
                $assignedUserId = ($scenario['assigned_to'] && $hasUsers)
                    ? (string) $users->get($index % $users->count(), $users->first())->id
                    : null;

                $protocol = sprintf('TK-SEED-%04d-%s', $index + 1, $tenant->tenant_code);

                // Find or create ticket by unique protocol
                $ticket = ChatTicket::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('protocol', $protocol)
                    ->first();

                if (! $ticket) {
                    $ticket = new ChatTicket;
                    $ticket->id = (string) Str::orderedUuid();
                    $ticket->tenant_id = $tenant->id;
                    $ticket->protocol = $protocol;
                }

                $ticket->fill([
                    'instance_id' => $instance->id,
                    'contact_id' => $contact?->id,
                    'assigned_to' => $assignedUserId,
                    'protocol' => $protocol,
                    'channel' => 'whatsapp',
                    'remote_jid' => $remoteJid,
                    'phone' => $contact?->phone,
                    'phone_e164' => $contact?->whatsapp,
                    'push_name' => $contact?->name,
                    'status' => $scenario['status'],
                    'priority' => $scenario['priority'],
                    'category' => 'atendimento',
                    'is_bot_active' => $scenario['is_bot_active'],
                    'started_at' => $baseDate,
                    'first_response_at' => $baseDate->copy()->addMinutes(3),
                    'last_message_at' => $baseDate->copy()->addMinutes(20),
                    'closed_at' => $scenario['status'] === 'closed' ? $baseDate->copy()->addMinutes(25) : null,
                    'sentiment' => $scenario['sentiment'],
                    'sentiment_score' => $scenario['sentiment_score'],
                    'sentiment_updated_at' => now()->subMinutes(10),
                    'tags' => ['seed', $scenario['key']],
                    'metadata' => ['seed_key' => $scenario['key'], 'seeded' => true],
                    'subject' => $scenario['subject'],
                    'human_takeover_at' => $scenario['human_takeover_at'],
                ]);

                $ticket->save();

                ChatMessage::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('ticket_id', $ticket->id)
                    ->delete();

                foreach ($scenario['messages'] as $messageIndex => $messageData) {
                    [$direction, $type, $source, $fromContact, $content, $status] = $messageData;

                    $messageTime = $baseDate->copy()->addMinutes(($messageIndex + 1) * 5);

                    ChatMessage::query()->withoutGlobalScopes()->create([
                        'id' => (string) Str::orderedUuid(),
                        'tenant_id' => $tenant->id,
                        'ticket_id' => $ticket->id,
                        'user_id' => $fromContact ? null : $assignedUserId,
                        'contact_id' => $contact?->id,
                        'content' => $content,
                        'type' => $type,
                        'direction' => $direction,
                        'is_from_contact' => $fromContact,
                        'source' => $source,
                        'status' => $status,
                        'file_url' => in_array($type, ['image', 'audio', 'document'], true)
                            ? 'https://picsum.photos/seed/chat-seed/640/360'
                            : null,
                        'file_name' => match ($type) {
                            'image' => 'comprovante-seed.jpg',
                            'audio' => 'audio-seed.ogg',
                            'document' => 'contrato-seed.pdf',
                            default => null,
                        },
                        'mime_type' => match ($type) {
                            'image' => 'image/jpeg',
                            'audio' => 'audio/ogg',
                            'document' => 'application/pdf',
                            default => null,
                        },
                        'file_size' => in_array($type, ['image', 'audio', 'document'], true)
                            ? random_int(30000, 250000)
                            : null,
                        'external_id' => sprintf('seed-%s-%02d', $scenario['key'], $messageIndex + 1),
                        'metadata' => ['seed_key' => $scenario['key']],
                        'sent_at' => $messageTime,
                        'delivered_at' => in_array($status, ['delivered', 'read'], true) ? $messageTime->copy()->addSeconds(20) : null,
                        'read_at' => $status === 'read' ? $messageTime->copy()->addMinute() : null,
                    ]);
                }

                $ticket->update([
                    'last_message_at' => ChatMessage::query()->withoutGlobalScopes()
                        ->where('ticket_id', $ticket->id)
                        ->max('created_at') ?? now(),
                ]);
            }

            $seededTenants++;
        }

        $adminTenant = PlatformTenant::query()->withoutGlobalScopes()
            ->where('tenant_code', 'AGENTFLX')
            ->orWhere('primary_email', 'admin@agentflix.com.br')
            ->first();

        if ($adminTenant) {
            $adminTenantTickets = ChatTicket::query()->withoutGlobalScopes()
                ->where('tenant_id', $adminTenant->id)
                ->count();

            if ($adminTenantTickets < 4) {
                $instance = ChatInstance::query()->withoutGlobalScopes()->firstOrCreate(
                    [
                        'tenant_id' => $adminTenant->id,
                        'name' => 'Default Uazapi',
                    ],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'provider' => 'uazapi',
                        'mode' => 'production',
                        'status' => 'connected',
                        'is_active' => true,
                        'evaluation_enabled' => true,
                        'webhook_token' => (string) Str::random(40),
                        'settings_json' => ['seeded' => true],
                        'last_status_at' => now(),
                    ],
                );

                $contact = CRMContact::query()->withoutGlobalScopes()
                    ->where('tenant_id', $adminTenant->id)
                    ->where('is_active', true)
                    ->first();

                if (! $contact) {
                    $contact = CRMContact::query()->withoutGlobalScopes()->create([
                        'id' => (string) Str::orderedUuid(),
                        'tenant_id' => $adminTenant->id,
                        'name' => 'Contato Seed AGENTFLX',
                        'phone' => '+5511999999999',
                        'whatsapp' => '5511999999999',
                        'email' => 'seed.chat.agentflx@agentflix.local',
                        'is_active' => true,
                    ]);
                }

                $fallbackStatuses = ['pending', 'open', 'in_progress', 'closed'];
                $statusesToCreate = array_slice($fallbackStatuses, (int) $adminTenantTickets);

                foreach ($statusesToCreate as $offset => $status) {
                    $index = (int) $adminTenantTickets + $offset;
                    $ticketId = (string) Str::orderedUuid();
                    $fallbackJid = sprintf('55119999999%02d@s.whatsapp.net', $index + 1);

                    ChatTicket::query()->withoutGlobalScopes()->create([
                        'id' => $ticketId,
                        'tenant_id' => $adminTenant->id,
                        'instance_id' => $instance->id,
                        'contact_id' => $contact->id,
                        'protocol' => sprintf('CHAT-AGX-%04d', $index + 1),
                        'channel' => 'whatsapp',
                        'remote_jid' => $fallbackJid,
                        'phone' => $contact->phone,
                        'phone_e164' => $contact->whatsapp,
                        'push_name' => $contact->name,
                        'status' => $status,
                        'priority' => 'normal',
                        'is_group' => false,
                        'is_bot_active' => $status !== 'in_progress',
                        'last_message_at' => now()->subMinutes(($index + 1) * 3),
                        'closed_at' => $status === 'closed' ? now()->subMinute() : null,
                        'metadata' => ['seeded' => true, 'fallback' => true],
                        // Proxy
                        'subject' => '[SEED] AGENTFLX fallback '.strtoupper($status),
                    ]);

                    ChatMessage::query()->withoutGlobalScopes()->create([
                        'id' => (string) Str::orderedUuid(),
                        'tenant_id' => $adminTenant->id,
                        'ticket_id' => $ticketId,
                        'contact_id' => $contact->id,
                        'content' => 'Mensagem seed para validação de inbox AGENTFLX.',
                        'type' => 'text',
                        'direction' => 'incoming',
                        'is_from_contact' => true,
                        'source' => 'webhook',
                        'status' => 'read',
                        'metadata' => ['seeded' => true, 'fallback' => true],
                        'read_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info(sprintf('✅ Cenários de chat criados para %d tenant(s): %d tickets por tenant', $seededTenants, count($scenarios)));
    }
}
