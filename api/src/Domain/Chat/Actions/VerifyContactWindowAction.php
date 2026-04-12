<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Carbon\Carbon;
use Domain\Chat\DTOs\ContactWindowStatusDTO;
use Domain\Chat\Models\ChatMessage;

/**
 * Action para verificar se o contato está dentro da janela de 24h.
 *
 * A janela de 24h é calculada a partir da última mensagem enviada pelo cliente
 * (is_from_contact=true). Se a última mensagem do cliente foi há mais de 24h,
 * o envio de texto livre não é permitido — deve-se usar template.
 *
 * Regra: Exactly 24h = FORA da janela (texto livre NÃO permitido)
 * - DENTRO: created_at > now() - 24 hours
 * - FORA: created_at <= now() - 24 hours
 *
 * @category Actions
 */
final readonly class VerifyContactWindowAction
{
    /**
     * Verifica se o contato está dentro da janela de 24h.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $contactId  UUID do contato.
     * @return ContactWindowStatusDTO  Status da janela com canSendFreeText e lastMessageAt.
     */
    public function execute(string $tenantId, string $contactId): ContactWindowStatusDTO
    {
        $lastMessage = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('contact_id', $contactId)
            ->where('is_from_contact', true)
            ->orderByDesc('created_at')
            ->first();

        if (! $lastMessage) {
            return new ContactWindowStatusDTO(
                canSendFreeText: false,
                lastMessageAt: null,
            );
        }

        $cutoff = Carbon::now()->subHours(24);
        $lastMessageAt = $lastMessage->created_at;

        $canSendFreeText = $lastMessageAt->greaterThan($cutoff);

        return new ContactWindowStatusDTO(
            canSendFreeText: $canSendFreeText,
            lastMessageAt: $lastMessageAt,
        );
    }
}
