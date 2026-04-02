<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Models\AiConversationSummary;
use Domain\Chat\Models\ChatMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class AiSummarizationService
{
    private const SUMMARY_LIMIT = 4000;

    private const PROGRESSIVE_INTERVAL = 15;

    /**
     * Build rolling summary and persist it.
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
     * Progressive summarization every 15 messages.
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

    public function invalidateSummary(string $ticketId): void
    {
        Cache::forget($this->cacheKey($ticketId));
    }

    /**
     * @return array<int, string>
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

    private function cacheKey(string $ticketId): string
    {
        return sprintf('summary:%s', $ticketId);
    }
}
