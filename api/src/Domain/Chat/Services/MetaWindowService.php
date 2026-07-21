<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Models\ChatTicket;
use Illuminate\Support\Carbon;

/**
 * Serviço da janela de atendimento (customer service window) da Meta Cloud API.
 *
 * Persiste e renova a janela 24h (padrão) / 72h (CTWA — Click-to-WhatsApp Ads)
 * em `chat_tickets`, seguindo a semântica oficial da Meta:
 *
 * - Toda mensagem inbound do cliente renova a janela de 24h a partir do
 *   timestamp da própria mensagem (não do horário de processamento).
 * - Uma mensagem inbound com `referral` (lead de anúncio) abre uma janela
 *   de 72h em vez de 24h.
 * - A janela NUNCA encurta: `expires_at = GREATEST(atual, novo)`.
 * - O tipo `'72h'` só é rebaixado para `'24h'` quando a janela de 72h já
 *   não é mais maior que a nova janela calculada — nunca antes disso.
 * - A escrita (`save()`) só ocorre quando algo de fato muda, para não gerar
 *   eventos de realtime nem custo de CPU desnecessários.
 *
 * Ver `~/Documents/prompts/SKILLS/meta-whatsapp-expert/references/customer-service-window.md`.
 *
 * @category Services
 */
final class MetaWindowService
{
    /** Duração da janela de atendimento padrão, em horas. */
    private const int STANDARD_WINDOW_HOURS = 24;

    /** Duração da janela de atendimento CTWA (referral), em horas. */
    private const int CTWA_WINDOW_HOURS = 72;

    /**
     * Tipos de janela aceitos — espelha o CHECK constraint de `meta_window_type`
     * na migration (`chat_tickets_meta_window_type_check`).
     *
     * @var list<string>
     */
    private const array VALID_WINDOW_TYPES = ['24h', '72h'];

    /**
     * Renova a janela de atendimento a partir de uma mensagem inbound do cliente.
     *
     * Base do cálculo é o timestamp da própria mensagem; quando ausente ou
     * inválido, cai em `now()`. Presença de `referral` abre janela de 72h;
     * caso contrário, 24h. A janela nunca encurta (GREATEST).
     *
     * @param  ChatTicket  $ticket  Ticket já resolvido (nunca resolver por telefone/nome).
     * @param  string|null  $messageTimestamp  Timestamp ISO 8601 da mensagem inbound.
     * @param  array<string, mixed>|null  $referral  Dados de referral CTWA, quando presentes.
     */
    public function renewFromInbound(ChatTicket $ticket, ?string $messageTimestamp, ?array $referral): void
    {
        $baseTimestamp = $this->parseTimestampOrNow($messageTimestamp);
        $hasReferral = $referral !== null;
        $hours = $hasReferral ? self::CTWA_WINDOW_HOURS : self::STANDARD_WINDOW_HOURS;
        $windowType = $hasReferral ? '72h' : '24h';

        $newExpiresAt = $baseTimestamp->copy()->addHours($hours);

        $this->applyWindow($ticket, $newExpiresAt, $windowType, $referral);
    }

    /**
     * Aplica a janela de atendimento a partir de um status de mensagem de saída.
     *
     * Usado quando a Meta reporta `conversation.expiration_timestamp` (e o tipo
     * de origem correspondente) nos callbacks de status. Segue a mesma regra
     * de GREATEST do inbound — nunca encurta a janela vigente.
     *
     * Diferente de {@see renewFromInbound()}, aqui `$expiresAtIso` já é a
     * expiração ABSOLUTA reportada pela Meta — não uma base para somar horas.
     * Por isso um timestamp ausente/inválido não pode cair em `now()`: isso
     * faria a janela nascer já expirada em um ticket sem janela prévia (o
     * GREATEST não tem nada para comparar e proteger). Preferimos no-op e
     * logar a descartar silenciosamente uma expiração inventada. Da mesma
     * forma, `$windowType` chega de fora (gateway/Meta) e a coluna tem CHECK
     * constraint — validamos antes de tentar persistir, em vez de deixar
     * estourar exceção de banco dentro do fluxo de ingestão.
     *
     * @param  ChatTicket  $ticket  Ticket já resolvido.
     * @param  string  $expiresAtIso  Expiração absoluta da janela (ISO 8601).
     * @param  string  $windowType  Tipo da janela reportado pela Meta ('24h'|'72h').
     */
    public function applyFromStatus(ChatTicket $ticket, string $expiresAtIso, string $windowType): void
    {
        if (! in_array($windowType, self::VALID_WINDOW_TYPES, true)) {
            logger()->warning('[MetaWindowService] windowType inválido em applyFromStatus — ignorado', [
                'ticket_id' => (string) $ticket->id,
                'window_type' => $windowType,
            ]);

            return;
        }

        $newExpiresAt = $this->parseTimestampStrict($expiresAtIso);

        if ($newExpiresAt === null) {
            logger()->warning('[MetaWindowService] expiresAtIso ausente/inválido em applyFromStatus — ignorado', [
                'ticket_id' => (string) $ticket->id,
                'expires_at_iso' => $expiresAtIso,
            ]);

            return;
        }

        $this->applyWindow($ticket, $newExpiresAt, $windowType, null);
    }

    /**
     * Aplica a nova janela calculada ao ticket, respeitando GREATEST e o guard
     * de escrita redundante, e grava os campos de referral na mesma operação.
     *
     * @param  array<string, mixed>|null  $referral  Dados de referral CTWA a persistir, quando presentes.
     */
    private function applyWindow(ChatTicket $ticket, Carbon $newExpiresAt, string $newType, ?array $referral): void
    {
        /** @var Carbon|null $currentExpiresAt */
        $currentExpiresAt = $ticket->meta_window_expires_at;
        $currentType = $ticket->meta_window_type;

        $currentIsGreater = $currentExpiresAt !== null && $currentExpiresAt->greaterThan($newExpiresAt);

        $finalExpiresAt = $currentIsGreater ? $currentExpiresAt : $newExpiresAt;

        // O tipo '72h' só é preservado enquanto a janela de 72h vigente ainda
        // for maior que a nova janela calculada nesta chamada.
        $finalType = ($currentType === '72h' && $currentIsGreater) ? '72h' : $newType;

        // Comparação por segundo (a coluna é timestamp(0) — sem frações de segundo)
        // para não disparar save() por ruído de microssegundos do parsing.
        $expiresAtChanged = $currentExpiresAt === null
            || $currentExpiresAt->format('Y-m-d H:i:s') !== $finalExpiresAt->format('Y-m-d H:i:s');
        $typeChanged = $finalType !== $currentType;

        $dirty = $expiresAtChanged || $typeChanged;

        if ($referral !== null) {
            $referralFields = [
                'meta_referral_source_id' => $referral['source_id'] ?? null,
                'meta_referral_source_type' => $referral['source_type'] ?? null,
                'meta_referral_headline' => $referral['headline'] ?? null,
                'meta_referral_ctwa_clid' => $referral['ctwa_clid'] ?? null,
            ];

            foreach ($referralFields as $field => $value) {
                if ($ticket->{$field} !== $value) {
                    $dirty = true;
                }
            }
        }

        if (! $dirty) {
            return;
        }

        $ticket->meta_window_expires_at = $finalExpiresAt;
        $ticket->meta_window_type = $finalType;

        if ($referral !== null) {
            $ticket->meta_referral_source_id = $referral['source_id'] ?? null;
            $ticket->meta_referral_source_type = $referral['source_type'] ?? null;
            $ticket->meta_referral_headline = $referral['headline'] ?? null;
            $ticket->meta_referral_ctwa_clid = $referral['ctwa_clid'] ?? null;
        }

        $ticket->save();
    }

    /**
     * Faz o parse de um timestamp ISO 8601, com fallback para `now()` quando
     * o valor está ausente ou é inválido.
     *
     * Uso restrito a `renewFromInbound()`: ali o timestamp é só a BASE de um
     * cálculo (base + 24h/72h), então cair em `now()` ainda produz uma janela
     * futura válida.
     */
    private function parseTimestampOrNow(?string $timestamp): Carbon
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return Carbon::now();
        }

        try {
            return Carbon::parse($timestamp);
        } catch (\Throwable) {
            return Carbon::now();
        }
    }

    /**
     * Faz o parse estrito de um timestamp ISO 8601, retornando `null` quando
     * ausente ou inválido — sem fallback para `now()`.
     *
     * Uso restrito a `applyFromStatus()`: ali o timestamp É a expiração
     * absoluta, então um valor inválido deve resultar em no-op (ver docblock
     * de `applyFromStatus()`), nunca em `now()`.
     */
    private function parseTimestampStrict(string $timestamp): ?Carbon
    {
        if (trim($timestamp) === '') {
            return null;
        }

        try {
            return Carbon::parse($timestamp);
        } catch (\Throwable) {
            return null;
        }
    }
}
