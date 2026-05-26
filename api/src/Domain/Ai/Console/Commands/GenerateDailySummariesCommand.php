<?php

declare(strict_types=1);

namespace Domain\Ai\Console\Commands;

use Domain\Ai\Jobs\AiSummarizeConversationJob;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Console\Command;

/**
 * Comando para gerar resumos diários de conversas de IA.
 *
 * Enfileira AiSummarizeConversationJob para todos os tickets atualizados
 * no dia corrente. Executado diariamente às 02:00 via scheduler.
 */
final class GenerateDailySummariesCommand extends Command
{
    protected $signature = 'ai:generate-daily-summaries';

    protected $description = 'Generate daily AI conversation summaries for active tickets.';

    /**
     * Despacha jobs de sumarização para todos os tickets atualizados hoje.
     */
    public function handle(): int
    {
        $tickets = ChatTicket::query()
            ->whereDate('updated_at', today())
            ->whereNotNull('tenant_id')
            ->get(['id', 'tenant_id']);

        foreach ($tickets as $ticket) {
            AiSummarizeConversationJob::dispatch((string) $ticket->tenant_id, (string) $ticket->id);
        }

        $this->info(sprintf('Dispatched %d summary jobs.', $tickets->count()));

        return self::SUCCESS;
    }
}
