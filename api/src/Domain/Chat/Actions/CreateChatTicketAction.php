<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatTicketDTO;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketSequence;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Configuration\Events\TicketCreatedEvent;
use Domain\CRM\Models\CRMContact;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Criar novos tickets manualmente ou por webhook.
 *
 * Responsabilidades:
 * - Criar ticket a partir de DTO com auto-geração de protocol
 * - Auto-criar contato se necessário
 * - Encontrar ou criar ticket por remote_jid (idempotência por webhook)
 * - Publicar mapeamento remote_jid → ticket_id no Redis para fast path
 * - Emitir eventos de criação
 */
final class CreateChatTicketAction
{
    private const TICKET_MAPPING_CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly ChatActivityBroadcastService $activityBroadcast,
    ) {}

    /**
     * Criar um novo ticket manual.
     */
    public function create(string $tenantId, ChatTicketDTO $dto): ChatTicket
    {
        return DB::transaction(function () use ($tenantId, $dto): ChatTicket {
            $ticketNumber = ChatTicketSequence::next($tenantId, 'TK-');

            $contactId = $dto->contactId ?? $this->findOrCreateContact($tenantId, $dto);

            /** @var array<string, mixed> $data */
            $data = $dto->toArray();
            $data['contact_id'] = $contactId;

            $ticket = ChatTicket::query()->create([
                ...$data,
                'tenant_id' => $tenantId,
                'status' => 'pending',
                'protocol' => $ticketNumber,
                'last_message_at' => now(),
            ]);

            $ticket->load(['contact', 'instance']);

            $this->activityBroadcast->emit(
                (string) $ticket->id,
                [
                    [
                        'type' => 'ticket.new',
                        'data' => [
                            'ticket_id' => (string) $ticket->id,
                            'tenant_id' => (string) $tenantId,
                            'ticket' => $ticket->toArray(),
                        ],
                    ],
                ],
                (string) $tenantId
            );

            TicketCreatedEvent::dispatch((string) $tenantId, (string) $ticket->id);

            Cache::forget("chat_counts:{$tenantId}");

            return $ticket;
        });
    }

    /**
     * Encontrar ticket ativo por remote_jid ou criar novo se inexistente.
     *
     * Garante idempotência para webhooks ao aceitar a mesma conversa múltiplas vezes.
     */
    public function findOrCreateByRemoteJid(string $tenantId, ChatTicketDTO $dto): ChatTicket
    {
        if ($dto->remoteJid) {
            $query = ChatTicket::query()
                ->where('tenant_id', $tenantId)
                ->where('remote_jid', $dto->remoteJid)
                ->whereIn('status', ['pending', 'open', 'in_progress']);

            if ($dto->instanceId) {
                $query->where('instance_id', $dto->instanceId);
            }

            $existing = $query->first();

            if ($existing) {
                $existing->last_message_at = now();
                $existing->loadMissing('extended');

                if ($dto->profilePictureUrl && $existing->profile_picture_url !== $dto->profilePictureUrl) {
                    $existing->profile_picture_url = $dto->profilePictureUrl;

                    if ($existing->contact_id) {
                        CRMContact::query()
                            ->where('id', $existing->contact_id)
                            ->where('tenant_id', $tenantId)
                            ->whereNot('avatar_url', $dto->profilePictureUrl)
                            ->update(['avatar_url' => $dto->profilePictureUrl]);
                    }
                }

                $existing->save();
                $this->publishTicketMapping($tenantId, $dto->remoteJid, (string) $existing->id);

                return $existing;
            }
        }

        $created = $this->create($tenantId, $dto);

        if ($dto->remoteJid) {
            $this->publishTicketMapping($tenantId, $dto->remoteJid, (string) $created->id);
        }

        return $created;
    }

    /**
     * Encontrar ou criar contato baseado em telefone/whatsapp.
     *
     * @return string|null ID do contato
     */
    private function findOrCreateContact(string $tenantId, ChatTicketDTO $dto): ?string
    {
        $phone = $dto->phone ?? $dto->phoneE164;
        if (! $phone && $dto->remoteJid) {
            $phone = Str::before($dto->remoteJid, '@');
        }

        if (! $phone) {
            return null;
        }

        $cleanPhone = (string) preg_replace('/[^0-9]/', '', $phone);

        $contact = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($cleanPhone, $phone) {
                $query->where('whatsapp', 'like', "%{$cleanPhone}%")
                    ->orWhere('phone', 'like', "%{$cleanPhone}%")
                    ->orWhere('whatsapp', $phone)
                    ->orWhere('phone', $phone);
            })
            ->first();

        if ($contact) {
            if ($dto->profilePictureUrl && $contact->avatar_url !== $dto->profilePictureUrl) {
                $contact->avatar_url = $dto->profilePictureUrl;
                $contact->save();
            }

            return $contact->id;
        }

        $contactName = $dto->pushName ?? 'Contato WhatsApp';

        $contact = CRMContact::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => $contactName,
            'phone' => $phone,
            'whatsapp' => $cleanPhone,
            'is_active' => true,
            'avatar_url' => $dto->profilePictureUrl,
        ]);

        Log::info('[ChatTicketActions] Auto-created contact', [
            'contact_id' => $contact->id,
            'name' => $contactName,
            'phone' => $phone,
            'tenant_id' => $tenantId,
        ]);

        return $contact->id;
    }

    /**
     * Publicar mapeamento remote_jid → ticket_id no Redis.
     *
     * Permite gateway resolver ticket rapidamente sem fazer round-trip ao BD.
     */
    private function publishTicketMapping(string $tenantId, string $remoteJid, string $ticketId): void
    {
        $normalized = Str::of($remoteJid)->trim()->lower()->value();
        if ($normalized === '') {
            return;
        }

        $key = "chat.ticket_by_jid:{$normalized}";

        try {
            Redis::connection('gateway')->setex(
                $key,
                self::TICKET_MAPPING_CACHE_TTL_SECONDS,
                json_encode(['tenant_id' => $tenantId, 'ticket_id' => $ticketId], JSON_THROW_ON_ERROR)
            );
        } catch (\Throwable $exception) {
            Log::warning('ChatTicketActions: failed to publish ticket mapping', [
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketId,
                'remote_jid' => $normalized,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
