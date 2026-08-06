<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Carbon\Carbon;
use Domain\Chat\DTOs\ContactWindowStatusDTO;
use Domain\Chat\Enums\ChatTicketStatus;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;

/**
 * Action para verificar se o contato está dentro da janela de atendimento
 * (24h padrão ou 72h CTWA da Meta).
 *
 * Defesa em profundidade em 2 camadas (princípio 3 da skill meta-whatsapp-expert):
 *
 * - **Branch 1 (autoritativo):** usa `chat_tickets.meta_window_expires_at` do
 *   ticket do contexto solicitado, quando esse campo estiver no futuro. É o
 *   valor renovado pelo webhook a cada inbound ({@see \Domain\Chat\Services\MetaWindowService}).
 * - **Branch 2 (fallback):** quando o campo persistido está ausente ou no
 *   passado, cai no cálculo por mensagens (última inbound + 24h) — nunca se
 *   confia cegamente num timestamp que pode estar desatualizado.
 *
 * Regra do fallback: Exactly 24h = FORA da janela (texto livre NÃO permitido)
 * - DENTRO: created_at > now() - 24 hours
 * - FORA: created_at <= now() - 24 hours
 *
 * Escopo obrigatório (fail-closed):
 * - O ticket é resolvido por `ticketId` OU por (contactId + instanceId) —
 *   nunca por tenant + contato sozinhos (evita misturar ticket/instância/canal).
 * - Mensagens do fallback pertencem SOMENTE ao ticket do contexto.
 * - `type=system` nunca abre fallback.
 * - Contexto ausente (sem ticketId e sem instanceId) falha fechado.
 *
 * @category Actions
 */
final readonly class VerifyContactWindowAction
{
    /**
     * Verifica se o contato está dentro da janela de atendimento.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $contactId  UUID do contato.
     * @param  string|null  $ticketId  UUID do ticket do contexto, quando conhecido.
     * @param  string|null  $instanceId  UUID da instância Meta do contexto, quando conhecida.
     * @return ContactWindowStatusDTO Status da janela com canSendFreeText, lastMessageAt, expiresAt e windowType.
     */
    public function execute(
        string $tenantId,
        string $contactId,
        ?string $ticketId = null,
        ?string $instanceId = null,
    ): ContactWindowStatusDTO {
        // Contexto ausente → fail-closed: sem ticket/instância, texto livre NÃO é liberado.
        if ($ticketId === null && $instanceId === null) {
            return new ContactWindowStatusDTO(
                canSendFreeText: false,
                lastMessageAt: null,
            );
        }

        $ticket = $this->resolveContextTicket($tenantId, $contactId, $ticketId, $instanceId);

        if ($ticket === null) {
            return new ContactWindowStatusDTO(
                canSendFreeText: false,
                lastMessageAt: null,
            );
        }

        $lastMessage = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticket->id)
            ->where('is_from_contact', true)
            ->where('type', '!=', 'system')
            ->orderByDesc('created_at')
            ->first();

        $lastMessageAt = $lastMessage?->created_at;

        $persistedExpiresAt = $ticket->meta_window_expires_at;

        // Branch 1: campo persistido presente e ainda no futuro — autoritativo.
        if ($persistedExpiresAt !== null && $persistedExpiresAt->isFuture()) {
            return new ContactWindowStatusDTO(
                canSendFreeText: true,
                lastMessageAt: $lastMessageAt,
                expiresAt: $persistedExpiresAt,
                windowType: $ticket->meta_window_type,
            );
        }

        // Branch 2: fallback por mensagens do MESMO ticket — campo ausente ou no passado.
        if (! $lastMessageAt) {
            return new ContactWindowStatusDTO(
                canSendFreeText: false,
                lastMessageAt: null,
            );
        }

        $cutoff = Carbon::now()->subHours(24);
        $canSendFreeText = $lastMessageAt->greaterThan($cutoff);

        return new ContactWindowStatusDTO(
            canSendFreeText: $canSendFreeText,
            lastMessageAt: $lastMessageAt,
            expiresAt: $lastMessageAt->copy()->addHours(24),
            windowType: '24h',
        );
    }

    /**
     * Resolve o ticket do contexto solicitado.
     *
     * - Com `ticketId`: ticket por tenant + id — e, quando `instanceId` também
     *   é informado, o ticket PRECISA pertencer à instância (contexto
     *   divergente falha fechado).
     * - Sem `ticketId` mas com `instanceId`: ticket ativo do contato naquela
     *   instância — outro canal/instância não autoriza esta janela.
     *
     * @return ChatTicket|null Ticket do contexto ou null quando não existe.
     */
    private function resolveContextTicket(
        string $tenantId,
        string $contactId,
        ?string $ticketId,
        ?string $instanceId,
    ): ?ChatTicket {
        $query = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                ChatTicketStatus::OPEN->value,
                ChatTicketStatus::PENDING->value,
                ChatTicketStatus::IN_PROGRESS->value,
            ]);

        if ($ticketId !== null) {
            // Coerência do contexto: o ticket informado precisa pertencer ao
            // contato do parâmetro — divergência falha fechado (evita vazar a
            // janela de outro contato do mesmo tenant via endpoint HTTP).
            $query->where('id', $ticketId)
                ->where('contact_id', $contactId);
        } else {
            $query->where('contact_id', $contactId)
                ->where('instance_id', $instanceId);
        }

        $ticket = $query->orderByDesc('last_message_at')->first();

        // Coerência do contexto: ticket informado + instância informada devem
        // apontar para a mesma instância — senão falha fechado.
        if ($ticket !== null && $instanceId !== null && $ticketId !== null) {
            if ((string) $ticket->instance_id !== (string) $instanceId) {
                return null;
            }
        }

        return $ticket;
    }
}
