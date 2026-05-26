<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Chat\Models\ChatMessage;

/**
 * Gerencia a janela de contexto de conversas para a IA.
 *
 * Constrói um array de mensagens recentes (janela deslizante) com
 * tamanho adaptativo baseado no total de mensagens. Quando disponível,
 * prepende o resumo acumulado da conversa como mensagem de sistema.
 */
final class ContextWindowManagerService
{
    public function __construct(private readonly AiConversationSummaryService $summaryService) {}

    /**
     * Constrói a janela de contexto com mensagens recentes e resumo acumulado.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $ticketId  UUID do ticket.
     * @return array<int, array<string, mixed>> Mensagens formatadas para o LLM.
     */
    public function buildWindow(string $tenantId, string $ticketId): array
    {
        $totalMessages = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->count();

        $windowSize = $this->resolveWindowSize($totalMessages);

        $window = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->latest('created_at')
            ->limit($windowSize)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ChatMessage $message): array => [
                'id' => (string) $message->id,
                'role' => $message->is_from_contact ? 'user' : 'assistant',
                'content' => (string) ($message->content ?? ''),
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->all();

        $summaryText = $this->summaryService->getSummaryText($tenantId, $ticketId);

        if ($summaryText !== '') {
            array_unshift($window, [
                'role' => 'system',
                'content' => $summaryText,
                'kind' => 'summary',
            ]);
        }

        return $window;
    }

    /**
     * Define o tamanho da janela com base no volume total de mensagens.
     *
     * @param  int  $totalMessages  Total de mensagens do ticket.
     * @return int Número de mensagens recentes a incluir na janela.
     */
    private function resolveWindowSize(int $totalMessages): int
    {
        if ($totalMessages <= 10) {
            return 10;
        }

        if ($totalMessages <= 30) {
            return 20;
        }

        return 30;
    }
}
