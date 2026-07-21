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
 *   ticket ativo do contato, quando esse campo estiver no futuro. É o valor
 *   renovado pelo webhook a cada inbound ({@see \Domain\Chat\Services\MetaWindowService}).
 * - **Branch 2 (fallback):** quando o campo persistido está ausente ou no
 *   passado, cai no cálculo por mensagens (última inbound + 24h) — nunca se
 *   confia cegamente num timestamp que pode estar desatualizado.
 *
 * Regra do fallback: Exactly 24h = FORA da janela (texto livre NÃO permitido)
 * - DENTRO: created_at > now() - 24 hours
 * - FORA: created_at <= now() - 24 hours
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
     * @return ContactWindowStatusDTO Status da janela com canSendFreeText, lastMessageAt, expiresAt e windowType.
     */
    public function execute(string $tenantId, string $contactId): ContactWindowStatusDTO
    {
        $lastMessage = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('contact_id', $contactId)
            ->where('is_from_contact', true)
            ->orderByDesc('created_at')
            ->first();

        $lastMessageAt = $lastMessage?->created_at;

        $ticket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->where('contact_id', $contactId)
            ->whereIn('status', [
                ChatTicketStatus::OPEN->value,
                ChatTicketStatus::PENDING->value,
                ChatTicketStatus::IN_PROGRESS->value,
            ])
            ->orderByDesc('last_message_at')
            ->first();

        $persistedExpiresAt = $ticket?->meta_window_expires_at;

        // Branch 1: campo persistido presente e ainda no futuro — autoritativo.
        if ($persistedExpiresAt !== null && $persistedExpiresAt->isFuture()) {
            return new ContactWindowStatusDTO(
                canSendFreeText: true,
                lastMessageAt: $lastMessageAt,
                expiresAt: $persistedExpiresAt,
                windowType: $ticket?->meta_window_type,
            );
        }

        // Branch 2: fallback por mensagens — campo ausente ou no passado.
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
}
