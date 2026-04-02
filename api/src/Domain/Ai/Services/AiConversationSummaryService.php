<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Models\AiConversationSummary;

final class AiConversationSummaryService
{
    public function __construct(private readonly AiSummarizationService $summarizationService) {}

    public function summarize(string $tenantId, string $ticketId): AiConversationSummary
    {
        return $this->summarizationService->summarize($tenantId, $ticketId);
    }

    public function summarizeProgressive(string $tenantId, string $ticketId): ?AiConversationSummary
    {
        return $this->summarizationService->summarizeProgressive($tenantId, $ticketId);
    }

    public function invalidateSummary(string $ticketId): void
    {
        $this->summarizationService->invalidateSummary($ticketId);
    }

    public function getSummaryText(string $tenantId, string $ticketId): string
    {
        return $this->summarizationService->getSummaryText($tenantId, $ticketId);
    }
}
