<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Models\AiConversationSummary;
use Domain\Chat\Models\ChatMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Serviço de sumarização progressiva de conversas por ticket.
 *
 * Contexto: mantém um resumo acumulado (rolling summary) que combina o resumo
 * anterior com as mensagens mais recentes a cada PROGRESSIVE_INTERVAL mensagens.
 * O texto é cacheado no Redis por 24h para evitar consultas repetidas ao banco.
 */
final class AiSummarizationService
{
    private const SUMMARY_LIMIT = 4000;

    private const PROGRESSIVE_INTERVAL = 15;

    /**
     * Constrói o resumo acumulado e persiste no banco e no cache.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $ticketId  UUID do ticket.
     * @return AiConversationSummary Registro criado ou atualizado.
     */
    public function summarize(string $tenantId, string $ticketId): AiConversationSummary
    {
        $totalMessages = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->count();

        $batchLines = $this->latestMessageLines($tenantId, $ticketId, self::PROGRESSIVE_INTERVAL);
        $batchText = implode("\n", $batchLines);

        $existingSummary = $this->getSummaryText($tenantId, $ticketId);
        $summaryText = $this->mergeRollingSummary($existingSummary, $batchText);

        /** @var AiConversationSummary $summary */
        $summary = AiConversationSummary::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketId,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'summary' => $summaryText,
                'message_count' => $totalMessages,
                'generated_at' => now(),
            ],
        );

        Cache::put($this->cacheKey($ticketId), $summaryText, now()->addDay());

        return $summary;
    }

    /**
     * Executa sumarização progressiva a cada PROGRESSIVE_INTERVAL mensagens.
     *
     * Retorna null se o ticket ainda não possui mensagens suficientes para
     * acionar o próximo ciclo de sumarização.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $ticketId  UUID do ticket.
     * @return AiConversationSummary|null Resumo gerado ou null se não necessário.
     */
    public function summarizeProgressive(string $tenantId, string $ticketId): ?AiConversationSummary
    {
        $count = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->count();

        if ($count === 0) {
            return null;
        }

        if ($count % self::PROGRESSIVE_INTERVAL !== 0) {
            return null;
        }

        return $this->summarize($tenantId, $ticketId);
    }

    /**
     * Retorna o texto do resumo, priorizando o cache Redis antes de consultar o banco.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $ticketId  UUID do ticket.
     * @return string Texto do resumo ou string vazia se não existir.
     */
    public function getSummaryText(string $tenantId, string $ticketId): string
    {
        $cached = Cache::get($this->cacheKey($ticketId));
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $summary = AiConversationSummary::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->value('summary');

        $summaryText = is_string($summary) ? $summary : '';
        if ($summaryText !== '') {
            Cache::put($this->cacheKey($ticketId), $summaryText, now()->addDay());
        }

        return $summaryText;
    }

    /**
     * Invalida o cache do resumo para o ticket informado.
     *
     * @param  string  $ticketId  UUID do ticket.
     */
    public function invalidateSummary(string $ticketId): void
    {
        Cache::forget($this->cacheKey($ticketId));
    }

    /**
     * Retorna as linhas formatadas das últimas mensagens do ticket.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $ticketId  UUID do ticket.
     * @param  int  $limit  Quantidade de mensagens mais recentes a recuperar.
     * @return array<int, string> Linhas no formato "User: ..." ou "Agent: ...".
     */
    private function latestMessageLines(string $tenantId, string $ticketId, int $limit): array
    {
        return ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $message): string => ($message->is_from_contact ? 'User' : 'Agent').': '.trim((string) $message->content))
            ->filter(fn (string $line): bool => $line !== 'User:' && $line !== 'Agent:')
            ->values()
            ->all();
    }

    /**
     * Mescla o resumo existente com as mensagens mais recentes, respeitando o SUMMARY_LIMIT.
     *
     * @param  string  $existingSummary  Resumo acumulado anterior.
     * @param  string  $batchText  Texto das mensagens recentes.
     * @return string Resumo mesclado truncado ao limite configurado.
     */
    private function mergeRollingSummary(string $existingSummary, string $batchText): string
    {
        if ($existingSummary === '' && $batchText === '') {
            return '';
        }

        if ($existingSummary === '') {
            return Str::limit($batchText, self::SUMMARY_LIMIT, '...');
        }

        if ($batchText === '') {
            return Str::limit($existingSummary, self::SUMMARY_LIMIT, '...');
        }

        $merged = "Previous summary:\n{$existingSummary}\n\nRecent messages:\n{$batchText}";

        return Str::limit($merged, self::SUMMARY_LIMIT, '...');
    }

    /** Gera a chave de cache do resumo para o ticket informado. */
    private function cacheKey(string $ticketId): string
    {
        return sprintf('summary:%s', $ticketId);
    }
}
