<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Models\AiConversationSummary;

/**
 * Serviço de fachada para geração de resumos de conversa.
 *
 * Delega todas as operações para AiSummarizationService,
 * expondo uma API simplificada para controllers e outros serviços.
 */
final class AiConversationSummaryService
{
    public function __construct(private readonly AiSummarizationService $summarizationService) {}

    /**
     * Gera e persiste o resumo acumulado da conversa.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $ticketId  UUID do ticket.
     * @return AiConversationSummary Resumo criado ou atualizado.
     */
    public function summarize(string $tenantId, string $ticketId): AiConversationSummary
    {
        return $this->summarizationService->summarize($tenantId, $ticketId);
    }

    /**
     * Executa sumarização progressiva a cada intervalo configurado de mensagens.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $ticketId  UUID do ticket.
     * @return AiConversationSummary|null Resumo gerado ou null se não necessário.
     */
    public function summarizeProgressive(string $tenantId, string $ticketId): ?AiConversationSummary
    {
        return $this->summarizationService->summarizeProgressive($tenantId, $ticketId);
    }

    /**
     * Invalida o cache do resumo para o ticket informado.
     *
     * @param  string  $ticketId  UUID do ticket.
     */
    public function invalidateSummary(string $ticketId): void
    {
        $this->summarizationService->invalidateSummary($ticketId);
    }

    /**
     * Retorna o texto do resumo, priorizando o cache Redis.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $ticketId  UUID do ticket.
     * @return string Texto do resumo ou string vazia se não existir.
     */
    public function getSummaryText(string $tenantId, string $ticketId): string
    {
        return $this->summarizationService->getSummaryText($tenantId, $ticketId);
    }
}
