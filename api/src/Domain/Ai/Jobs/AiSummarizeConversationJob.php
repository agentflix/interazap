<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Domain\Ai\Services\AiConversationSummaryService;
use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job assíncrono para geração de resumo de uma conversa de IA.
 *
 * Enfileirado pelo AiConversationSummaryListener após cada run concluído
 * e pelo GenerateDailySummariesCommand para tickets atualizados no dia.
 *
 * @category Jobs
 */
final class AiSummarizeConversationJob implements ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $ticketId,
    ) {}

    /**
     * Invoca o serviço de sumarização para o ticket informado.
     */
    public function handle(AiConversationSummaryService $service): void
    {
        $service->summarize($this->tenantId, $this->ticketId);
    }
}
