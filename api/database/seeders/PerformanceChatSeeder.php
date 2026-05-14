<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Performance seed for Chat context.
 *
 * Seeds all 20 Chat tables per tenant using raw inserts.
 * Total: ~67,250 records across 50 tenants.
 * Critical: messages table uses batch size 2000 to avoid timeout.
 */
final class PerformanceChatSeeder
{
    use WithoutModelEvents;

    private const int BATCH_SIZE = 1000;

    private const int MESSAGE_BATCH_SIZE = 2000;

    public function seedForTenant(string $tenantId): void
    {
        $instanceIds = $this->seedInstances($tenantId);
        $this->seedTicketSequences($tenantId);

        $userIds = DB::table('auth_users')->where('tenant_id', $tenantId)->pluck('id')->toArray();
        $contactIds = DB::table('crm_contacts')->where('tenant_id', $tenantId)->pluck('id')->toArray();

        $ticketIds = $this->seedTickets($tenantId, $instanceIds, $userIds, $contactIds);
        $this->seedTicketsExtended($ticketIds);

        // Messages are the highest volume - use large batch
        $messageIds = $this->seedMessages($tenantId, $ticketIds, $userIds, $contactIds);
        $this->seedMessagesExtended($messageIds);

        $this->seedMessageTemplates($tenantId, $instanceIds);
        $this->seedTicketTransfers($tenantId, $ticketIds, $userIds);
        $this->seedMessageInteractions($tenantId, $messageIds, $userIds);
        $this->seedSessions($tenantId, $ticketIds, $contactIds);
        $this->seedAutoReplyRules($tenantId);
        $this->seedAutoReplyCooldowns($tenantId, $ticketIds);
        $this->seedTransmissionLists($tenantId, $instanceIds);
        $this->seedTransmissionListContacts($tenantId);
        $this->seedQuickAnswers($tenantId);
        $this->seedTicketEvaluations($tenantId);
        $this->seedRoutingQueues($tenantId, $instanceIds);
        $this->seedRoutingQueueAgents($tenantId, $userIds);
        $this->seedRoutingAgentSkills($tenantId, $userIds);
    }

    /** @return array<int, string> */
    private function seedInstances(string $tenantId): array
    {
        $providers = ['whatsapp', 'meta', 'telegram'];
        $statusWeights = ['active' => 50, 'disconnected' => 20, 'connecting' => 15, 'error' => 15];

        $instances = [];
        $ids = [];
        $count = random_int(2, 3);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $status = PerformanceSeeder::weightedRandom($statusWeights);

            $instances[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'provider' => $providers[array_rand($providers)],
                'name' => 'Instance '.($i + 1).' — '.Str::random(4),
                'mode' => ['production', 'sandbox'][array_rand(['production', 'sandbox'])],
                'status' => $status,
                'is_active' => $status === 'active' || (bool) random_int(0, 1),
                'evaluation_enabled' => (bool) random_int(0, 1),
                'evaluation_cutoff_score' => random_int(1, 5),
                'webhook_token' => (string) Str::uuid(),
                'settings_json' => json_encode(['debug' => false]),
                'auto_close_enabled' => (bool) random_int(0, 1),
                'auto_close_after_minutes' => random_int(30, 1440),
                'auto_close_target' => ['resolved', 'none'][array_rand(['resolved', 'none'])],
                'auto_close_message' => 'Obrigado pelo contato!',
                'last_status_at' => PerformanceSeeder::randomDate(),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('chat_instances', $instances, self::BATCH_SIZE);

        return $ids;
    }

    private function seedTicketSequences(string $tenantId): void
    {
        DB::table('chat_ticket_sequences')->insert([
            'id' => PerformanceSeeder::uuid(),
            'tenant_id' => $tenantId,
            'current_value' => random_int(100, 1000),
            'prefix' => 'SUP-',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<int, string> */
    private function seedTickets(string $tenantId, array $instanceIds, array $userIds, array $contactIds): array
    {
        $statusWeights = ['open' => 25, 'pending' => 25, 'closed' => 50];
        $priorities = ['low' => 20, 'normal' => 50, 'high' => 20, 'urgent' => 10];
        $channels = ['whatsapp' => 70, 'telegram' => 15, 'web' => 10, 'meta' => 5];
        $sentiments = ['positive' => 30, 'neutral' => 50, 'negative' => 20];
        $categories = ['Suporte', 'Vendas', 'Financeiro', 'Reclamacao', 'Duvida', 'Sugestao'];

        $tickets = [];
        $ids = [];
        $count = random_int(80, 120);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $status = PerformanceSeeder::weightedRandom($statusWeights);
            $isClosed = $status === 'closed';
            $hasFirstResponse = random_int(0, 100) > 20;
            $hasContact = $contactIds !== [] && random_int(0, 100) > 10;
            $lastMessageAt = PerformanceSeeder::randomDate();

            $tickets[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'contact_id' => $hasContact ? $contactIds[array_rand($contactIds)] : null,
                'instance_id' => $instanceIds === [] ? null : $instanceIds[array_rand($instanceIds)],
                'assigned_to' => $userIds !== [] && (bool) random_int(0, 1) ? $userIds[array_rand($userIds)] : null,
                'current_ai_agent_id' => null, // Will be seeded by AI seeder
                'protocol' => 'SUP-'.str_pad((string) random_int(1000, 99999), 5, '0', STR_PAD_LEFT),
                'channel' => PerformanceSeeder::weightedRandom($channels),
                'remote_jid' => '55'.random_int(1100000000, 99999999999).'@s.whatsapp.net',
                'phone' => '+55'.random_int(1100000000, 99999999999),
                'phone_e164' => '+55'.random_int(1100000000, 99999999999),
                'push_name' => fake('pt_BR')->name(),
                'status' => $status,
                'priority' => PerformanceSeeder::weightedRandom($priorities),
                'category' => $categories[array_rand($categories)],
                'is_group' => random_int(0, 100) > 90,
                'is_bot_active' => (bool) random_int(0, 1),
                'started_at' => PerformanceSeeder::randomDate(),
                'first_response_at' => $hasFirstResponse ? $lastMessageAt->copy()->addMinutes(random_int(1, 60)) : null,
                'last_message_at' => $lastMessageAt,
                'last_customer_message_at' => $lastMessageAt,
                'last_agent_message_at' => $hasFirstResponse ? $lastMessageAt->copy()->addMinutes(random_int(5, 120)) : null,
                'closed_at' => $isClosed ? $lastMessageAt->copy()->addHours(random_int(1, 48)) : null,
                'closed_mode' => $isClosed ? ['manual', 'auto'][array_rand(['manual', 'auto'])] : null,
                'sentiment' => PerformanceSeeder::weightedRandom($sentiments),
                'sentiment_score' => random_int(-100, 100),
                'sentiment_updated_at' => $lastMessageAt,
                'tags' => json_encode([]),
                'metadata' => json_encode(['source' => 'webhook']),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
                'deleted_at' => random_int(0, 100) > 95 ? now()->subDays(random_int(1, 30)) : null,
            ];
        }

        PerformanceSeeder::insertBatch('chat_tickets', $tickets, self::BATCH_SIZE);

        return $ids;
    }

    private function seedTicketsExtended(array $ticketIds): void
    {
        $extended = [];

        foreach ($ticketIds as $ticketId) {
            $hasSLA = (bool) random_int(0, 1);

            $extended[] = [
                'id' => PerformanceSeeder::uuid(),
                'ticket_id' => $ticketId,
                'subject' => 'Assunto do ticket '.random_int(1000, 9999),
                'profile_picture_url' => null,
                'human_takeover_at' => random_int(0, 100) > 60 ? PerformanceSeeder::randomDate() : null,
                'closed_by' => null,
                'close_reason' => ['resolved', 'spam', 'abandoned', 'transferred'][array_rand(['resolved', 'spam', 'abandoned', 'transferred'])],
                'auto_close_queue_after_minutes' => random_int(15, 120),
                'auto_close_in_progress_after_minutes' => random_int(60, 480),
                'sla_first_response_due_at' => $hasSLA ? now()->addMinutes(random_int(5, 60)) : null,
                'sla_resolution_due_at' => $hasSLA ? now()->addHours(random_int(1, 24)) : null,
                'sla_first_response_breached' => (bool) random_int(0, 1),
                'sla_resolution_breached' => (bool) random_int(0, 1),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('chat_tickets_extended', $extended, self::BATCH_SIZE);
    }

    /** @return array<int, string> */
    private function seedMessages(string $tenantId, array $ticketIds, array $userIds, array $contactIds): array
    {
        $types = ['text' => 60, 'image' => 15, 'audio' => 10, 'video' => 5, 'document' => 5, 'location' => 3, 'template' => 2];
        $directions = ['inbound' => 50, 'outbound' => 50];
        $statuses = ['pending' => 5, 'sent' => 20, 'delivered' => 30, 'read' => 40, 'failed' => 5];
        $sources = ['webhook' => 70, 'api' => 15, 'manual' => 10, 'bot' => 5];

        $messages = [];
        $ids = [];

        // ~400-600 messages per tenant
        foreach ($ticketIds as $ticketId) {
            $msgCount = random_int(5, 15);
            $lastDate = PerformanceSeeder::randomDate();

            for ($m = 0; $m < $msgCount; $m++) {
                $id = PerformanceSeeder::uuid();
                $ids[] = $id;
                $type = PerformanceSeeder::weightedRandom($types);
                $direction = PerformanceSeeder::weightedRandom($directions);
                $isFromContact = $direction === 'inbound';
                $status = PerformanceSeeder::weightedRandom($statuses);
                $sentAt = $lastDate->copy()->addMinutes($m * random_int(5, 30));
                $isDeleted = random_int(0, 100) > 95;

                $messages[] = [
                    'id' => $id,
                    'tenant_id' => $tenantId,
                    'ticket_id' => $ticketId,
                    'user_id' => ! $isFromContact && $userIds !== [] ? $userIds[array_rand($userIds)] : null,
                    'contact_id' => $isFromContact && $contactIds !== [] ? $contactIds[array_rand($contactIds)] : null,
                    'content' => $type === 'text' ? fake('pt_BR')->sentence() : null,
                    'type' => $type,
                    'direction' => $direction,
                    'is_from_contact' => $isFromContact,
                    'source' => array_rand($sources),
                    'status' => $status,
                    'external_id' => (string) Str::uuid(),
                    'metadata' => json_encode(['raw' => true]),
                    'sent_at' => $sentAt,
                    'delivered_at' => in_array($status, ['delivered', 'read']) ? $sentAt->copy()->addSeconds(random_int(1, 60)) : null,
                    'read_at' => $status === 'read' ? $sentAt->copy()->addMinutes(random_int(1, 60)) : null,
                    'is_deleted' => $isDeleted,
                    'deleted_at' => $isDeleted ? $sentAt->copy()->addMinutes(random_int(1, 60)) : null,
                    'deleted_by' => $isDeleted && $userIds !== [] ? $userIds[array_rand($userIds)] : null,
                    'transcription' => $type === 'audio' ? 'Transcricao do audio '.random_int(1, 100) : null,
                    'audio_duration_ms' => $type === 'audio' ? random_int(1000, 60000) : null,
                    'audio_mime_type' => $type === 'audio' ? 'audio/ogg; codecs=opus' : null,
                    'created_at' => $sentAt,
                    'updated_at' => now(),
                ];
            }
        }

        PerformanceSeeder::insertBatch('chat_messages', $messages, self::MESSAGE_BATCH_SIZE);

        return $ids;
    }

    private function seedMessagesExtended(array $messageIds): void
    {
        $extended = [];
        $mimeTypes = ['image/jpeg', 'image/png', 'application/pdf', 'audio/ogg', 'video/mp4'];

        foreach ($messageIds as $messageId) {
            if (random_int(0, 100) > 60) { // 40% have extended data
                $hasFile = (bool) random_int(0, 1);
                $isEdited = random_int(0, 100) > 95;
                $hasTranscription = random_int(0, 100) > 70;

                $extended[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'message_id' => $messageId,
                    'file_url' => $hasFile ? 'https://cdn.perf.local/media/'.random_int(1000, 9999).'.bin' : null,
                    'file_name' => $hasFile ? 'file_'.random_int(1000, 9999).['.jpg', '.png', '.pdf', '.mp4'][array_rand(['.jpg', '.png', '.pdf', '.mp4'])] : null,
                    'mime_type' => $hasFile ? $mimeTypes[array_rand($mimeTypes)] : null,
                    'file_size' => $hasFile ? random_int(1000, 10_000_000) : null,
                    'media_transcription_status' => $hasTranscription ? 'completed' : null,
                    'media_transcription_provider' => $hasTranscription ? 'openai' : null,
                    'media_transcribed_at' => $hasTranscription ? PerformanceSeeder::randomDate() : null,
                    'media_transcription' => $hasTranscription ? fake('pt_BR')->sentence() : null,
                    'media_transcription_cost' => $hasTranscription ? random_int(1, 50) / 10000 : null,
                    'media_transcription_tokens' => $hasTranscription ? random_int(100, 5000) : null,
                    'reactions' => random_int(0, 100) > 80 ? json_encode(['👍' => random_int(1, 5)]) : null,
                    'is_edited' => $isEdited,
                    'edited_at' => $isEdited ? PerformanceSeeder::randomDate() : null,
                    'edit_history' => $isEdited ? json_encode(['original' => 'texto original']) : null,
                    'error_message' => random_int(0, 100) > 95 ? 'Erro no envio da mensagem' : null,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($extended !== []) {
            PerformanceSeeder::insertBatch('chat_messages_extended', $extended, self::MESSAGE_BATCH_SIZE);
        }
    }

    private function seedMessageTemplates(string $tenantId, array $instanceIds): void
    {
        $statuses = ['approved' => 70, 'pending' => 20, 'rejected' => 10];
        $providers = ['local' => 60, 'meta' => 40];
        $categories = ['marketing', 'utility', 'authentication', 'onboarding'];
        $templates = [];
        $count = random_int(8, 12);

        for ($i = 0; $i < $count; $i++) {
            $status = PerformanceSeeder::weightedRandom($statuses);
            $templates[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'chat_instance_id' => $instanceIds === [] ? null : $instanceIds[array_rand($instanceIds)],
                'name' => 'template_'.random_int(100, 999),
                'shortcut' => '/t'.random_int(1, 99),
                'content' => fake('pt_BR')->sentence(),
                'provider' => PerformanceSeeder::weightedRandom($providers),
                'external_id' => (string) Str::uuid(),
                'language' => 'pt_BR',
                'category' => $categories[array_rand($categories)],
                'status' => $status,
                'rejected_reason' => $status === 'rejected' ? 'Formato invalido' : null,
                'components_json' => json_encode(['type' => 'body']),
                'last_synced_at' => PerformanceSeeder::randomDate(),
                'is_active' => $status === 'approved',
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
                'deleted_at' => random_int(0, 100) > 95 ? now()->subDays(random_int(1, 30)) : null,
            ];
        }

        PerformanceSeeder::insertBatch('chat_message_templates', $templates, self::BATCH_SIZE);
    }

    private function seedTicketTransfers(string $tenantId, array $ticketIds, array $userIds): void
    {
        $statuses = ['pending' => 20, 'accepted' => 70, 'rejected' => 10];
        $transfers = [];
        $count = min(count($ticketIds), 15);

        for ($i = 0; $i < $count; $i++) {
            if ($userIds === [] || count($userIds) < 2) {
                break;
            }

            $fromUser = $userIds[array_rand($userIds)];
            $toUser = $userIds[array_rand($userIds)];
            while ($toUser === $fromUser) {
                $toUser = $userIds[array_rand($userIds)];
            }

            $transfers[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketIds[array_rand($ticketIds)],
                'from_user_id' => $fromUser,
                'to_user_id' => $toUser,
                'reason' => 'Transferencia por '.['especialidade', 'carga de trabalho', 'ausencia', 'escalonamento'][array_rand(['especialidade', 'carga de trabalho', 'ausencia', 'escalonamento'])],
                'status' => PerformanceSeeder::weightedRandom($statuses),
                'transferred_at' => PerformanceSeeder::randomDate(),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        if ($transfers !== []) {
            PerformanceSeeder::insertBatch('chat_ticket_transfers', $transfers, self::BATCH_SIZE);
        }
    }

    private function seedMessageInteractions(string $tenantId, array $messageIds, array $userIds): void
    {
        $types = ['button_reply' => 50, 'list_reply' => 30, 'quick_reply' => 20];
        $interactions = [];
        $count = min(count($messageIds), 50);

        for ($i = 0; $i < $count; $i++) {
            $interactions[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'message_id' => $messageIds[array_rand($messageIds)],
                'interaction_type' => PerformanceSeeder::weightedRandom($types),
                'user_id' => $userIds === [] ? null : $userIds[array_rand($userIds)],
                'data' => json_encode(['button_id' => 'btn_'.random_int(1, 10)]),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        if ($interactions !== []) {
            PerformanceSeeder::insertBatch('chat_message_interactions', $interactions, self::BATCH_SIZE);
        }
    }

    private function seedSessions(string $tenantId, array $ticketIds, array $contactIds): void
    {
        $sessions = [];
        $count = min(count($ticketIds), 20);

        for ($i = 0; $i < $count; $i++) {
            $sessions[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'contact_id' => $contactIds === [] ? null : $contactIds[array_rand($contactIds)],
                'ticket_id' => $ticketIds[array_rand($ticketIds)],
                'token' => (string) Str::uuid(),
                'client_info' => json_encode(['ip' => fake('pt_BR')->ipv4(), 'ua' => 'Mozilla/5.0']),
                'last_activity_at' => PerformanceSeeder::randomDate(),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('chat_sessions', $sessions, self::BATCH_SIZE);
    }

    private function seedAutoReplyRules(string $tenantId): void
    {
        $triggers = ['ola', 'oi', 'bom dia', 'boa tarde', 'ajuda', 'suporte', 'preco', 'teste'];
        $rules = [];
        $count = random_int(10, 20);

        for ($i = 0; $i < $count; $i++) {
            $rules[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Regra '.($i + 1),
                'trigger_text' => $triggers[$i % count($triggers)],
                'response_text' => fake('pt_BR')->sentence(),
                'is_active' => random_int(0, 100) > 10,
                'is_welcome' => $i === 0,
                'cooldown_seconds' => random_int(0, 3600),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('chat_auto_reply_rules', $rules, self::BATCH_SIZE);
    }

    private function seedAutoReplyCooldowns(string $tenantId, array $ticketIds): void
    {
        $ruleIds = DB::table('chat_auto_reply_rules')->where('tenant_id', $tenantId)->pluck('id')->toArray();
        $cooldowns = [];
        $count = min(count($ticketIds) * 2, 30);

        for ($i = 0; $i < $count; $i++) {
            $cooldowns[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketIds[array_rand($ticketIds)],
                'rule_id' => $ruleIds[array_rand($ruleIds)] ?? null,
                'cooldown_until' => now()->addSeconds(random_int(1, 3600)),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('chat_auto_reply_cooldowns', $cooldowns, self::BATCH_SIZE);
    }

    private function seedTransmissionLists(string $tenantId, array $instanceIds): void
    {
        $statuses = ['draft' => 30, 'scheduled' => 20, 'sending' => 10, 'sent' => 30, 'cancelled' => 10];
        $lists = [];
        $count = random_int(3, 7);

        for ($i = 0; $i < $count; $i++) {
            $status = PerformanceSeeder::weightedRandom($statuses);
            $lists[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'instance_id' => $instanceIds === [] ? null : $instanceIds[array_rand($instanceIds)],
                'name' => 'Lista '.($i + 1),
                'message' => fake('pt_BR')->sentence(),
                'filter_criteria' => json_encode(['tags' => []]),
                'status' => $status,
                'scheduled_at' => in_array($status, ['scheduled', 'sending', 'sent']) ? now()->subDays(random_int(1, 30)) : null,
                'metadata' => json_encode(['total' => random_int(10, 1000)]),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('chat_transmission_lists', $lists, self::BATCH_SIZE);
    }

    private function seedTransmissionListContacts(string $tenantId): void
    {
        $listIds = DB::table('chat_transmission_lists')->where('tenant_id', $tenantId)->pluck('id')->toArray();
        $contactIds = DB::table('crm_contacts')->where('tenant_id', $tenantId)->pluck('id')->toArray();
        $contacts = [];

        foreach ($listIds as $listId) {
            $contactCount = min(random_int(5, 15), count($contactIds));
            for ($c = 0; $c < $contactCount; $c++) {
                $status = ['pending' => 30, 'sent' => 60, 'failed' => 10];
                $s = PerformanceSeeder::weightedRandom($status);

                $contacts[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tenant_id' => $tenantId,
                    'transmission_list_id' => $listId,
                    'contact_id' => $contactIds[array_rand($contactIds)] ?? null,
                    'status' => $s,
                    'sent_at' => $s === 'sent' ? PerformanceSeeder::randomDate() : null,
                    'error' => $s === 'failed' ? 'Falha no envio' : null,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        PerformanceSeeder::insertBatch('chat_transmission_list_contacts', $contacts, self::BATCH_SIZE);
    }

    private function seedQuickAnswers(string $tenantId): void
    {
        $categories = ['Geral', 'Suporte', 'Vendas', 'Financeiro', 'Onboarding'];
        $answers = [];
        $count = random_int(15, 25);

        for ($i = 0; $i < $count; $i++) {
            $answers[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Resposta '.($i + 1),
                'shortcut' => '/r'.($i + 1),
                'content' => fake('pt_BR')->paragraph(),
                'category' => $categories[array_rand($categories)],
                'is_active' => random_int(0, 100) > 10,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
                'deleted_at' => random_int(0, 100) > 95 ? now()->subDays(random_int(1, 30)) : null,
            ];
        }

        PerformanceSeeder::insertBatch('chat_quick_answers', $answers, self::BATCH_SIZE);
    }

    private function seedTicketEvaluations(string $tenantId): void
    {
        $evaluations = [];
        $closedTicketIds = DB::table('chat_tickets')
            ->where('tenant_id', $tenantId)
            ->where('status', 'closed')
            ->pluck('id')
            ->toArray();

        $count = min(count($closedTicketIds), 20);
        for ($i = 0; $i < $count; $i++) {
            $hasComment = random_int(0, 100) > 50;
            $evaluations[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'ticket_id' => $closedTicketIds[array_rand($closedTicketIds)],
                'token' => (string) Str::uuid(),
                'rating' => random_int(1, 5),
                'comment' => $hasComment ? fake('pt_BR')->sentence() : null,
                'submitted_at' => PerformanceSeeder::randomDate(),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        if ($evaluations !== []) {
            PerformanceSeeder::insertBatch('chat_ticket_evaluations', $evaluations, self::BATCH_SIZE);
        }
    }

    private function seedRoutingQueues(string $tenantId, array $instanceIds): void
    {
        $strategies = ['round_robin' => 50, 'least_busy' => 30, 'skill_based' => 20];
        $queues = [];
        $count = random_int(2, 4);

        for ($i = 0; $i < $count; $i++) {
            $queues[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'instance_id' => $instanceIds === [] ? null : $instanceIds[array_rand($instanceIds)],
                'name' => 'Fila '.($i + 1),
                'is_enabled' => random_int(0, 100) > 20,
                'strategy' => PerformanceSeeder::weightedRandom($strategies),
                'max_open_tickets_per_agent' => random_int(3, 15),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('chat_routing_queues', $queues, self::BATCH_SIZE);
    }

    private function seedRoutingQueueAgents(string $tenantId, array $userIds): void
    {
        $queueIds = DB::table('chat_routing_queues')->where('tenant_id', $tenantId)->pluck('id')->toArray();
        $agents = [];

        foreach ($queueIds as $queueId) {
            $agentCount = min(random_int(2, 5), count($userIds));
            for ($a = 0; $a < $agentCount; $a++) {
                $agents[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'queue_id' => $queueId,
                    'user_id' => $userIds[array_rand($userIds)],
                    'position' => $a,
                    'last_assigned_at' => PerformanceSeeder::randomDate(),
                    'is_active' => random_int(0, 100) > 10,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($agents !== []) {
            PerformanceSeeder::insertBatch('chat_routing_queue_agents', $agents, self::BATCH_SIZE);
        }
    }

    private function seedRoutingAgentSkills(string $tenantId, array $userIds): void
    {
        $queueIds = DB::table('chat_routing_queues')->where('tenant_id', $tenantId)->pluck('id')->toArray();
        $skills = ['vendas', 'suporte_tecnico', 'financeiro', 'onboarding', 'reclamacao', 'retencao'];
        $agentSkills = [];

        foreach ($queueIds as $queueId) {
            $skillCount = random_int(3, 6);
            for ($s = 0; $s < $skillCount; $s++) {
                $agentSkills[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'queue_id' => $queueId,
                    'user_id' => $userIds[array_rand($userIds)] ?? null,
                    'skill' => $skills[$s % count($skills)],
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        PerformanceSeeder::insertBatch('chat_routing_agent_skills', $agentSkills, self::BATCH_SIZE);
    }
}
