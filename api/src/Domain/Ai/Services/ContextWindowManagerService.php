<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Chat\Models\ChatMessage;

/**
 * Service for managing AI conversation context windows.
 *
 * @category Services
 */
final class ContextWindowManagerService
{
    public function __construct(private readonly AiConversationSummaryService $summaryService) {}

    /**
     * @return array<int, array<string, mixed>>
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
